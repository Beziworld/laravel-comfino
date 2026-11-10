<?php

declare(strict_types=1);

namespace Beziworld\Comfino\Http\Controllers;

use Beziworld\Comfino\ComfinoManager;
use Beziworld\Comfino\Enums\OrderStatus;
use Beziworld\Comfino\Events\OrderAccepted;
use Beziworld\Comfino\Events\OrderCancelled;
use Beziworld\Comfino\Events\OrderPaid;
use Beziworld\Comfino\Events\OrderRejected;
use Beziworld\Comfino\Events\OrderStatusChanged;
use Beziworld\Comfino\Exceptions\InvalidConfiguration;
use Beziworld\Comfino\Exceptions\InvalidNotification;
use Beziworld\Comfino\Support\Signature;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Receives ComfinoPay status notifications for the connection named in the URL.
 *
 * ComfinoPay sends notifications with PUT (POST after HTTP 405) and repeats
 * them until it receives HTTP 200. The CR-Signature header is verified with
 * the API key of the connection over the raw body; forged notifications and
 * disallowed senders get HTTP 403, malformed bodies HTTP 400. Authentic
 * notifications are acknowledged with HTTP 200 after the events are
 * dispatched; an exception in a listener results in HTTP 500 and a redelivery.
 */
final class NotificationController
{
    public function __construct(
        private readonly ComfinoManager $manager,
        private readonly Config $settings,
        private readonly Dispatcher $events,
        private readonly Cache $cache,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(Request $request, ?string $connection = null): Response
    {
        $name = $connection ?? $this->manager->getDefaultConnection();
        $body = $request->getContent();
        $signature = $request->headers->get(Signature::HEADER);

        if ($signature === null || trim($signature) === '') {
            $signature = $request->headers->get(Signature::LEGACY_HEADER);
        }

        try {
            $this->checkSender($request);
            $client = $this->manager->connection($name);
            $notification = $client->parseNotification(
                $body,
                $signature,
                (bool) $this->settings->get('comfino.notifications.verify_signature', true),
            );
        } catch (InvalidNotification $exception) {
            $this->logger->warning('Rejected ComfinoPay notification: ' . $exception->getMessage(), ['connection' => $name, 'ip' => $request->ip()]);

            return new Response('', $exception->isAuthenticationFailure() ? 403 : 400);
        } catch (InvalidConfiguration $exception) {
            $this->logger->warning('Rejected ComfinoPay notification: ' . $exception->getMessage(), ['connection' => $name, 'ip' => $request->ip()]);

            return new Response('', 403);
        }

        $ttl = (int) $this->settings->get('comfino.notifications.deduplicate_ttl', 0);
        $key = 'comfino:notification:' . hash('sha256', $name . "\n" . $body);

        if ($ttl > 0 && $this->cache->has($key)) {
            return new Response('', 200);
        }

        $this->events->dispatch(new OrderStatusChanged($notification, $name));

        $event = match ($notification->status) {
            OrderStatus::Accepted, OrderStatus::WaitingForPayment => new OrderAccepted($notification, $name),
            OrderStatus::Paid => new OrderPaid($notification, $name),
            OrderStatus::Rejected => new OrderRejected($notification, $name),
            OrderStatus::CancelledByShop, OrderStatus::Cancelled, OrderStatus::Resign => new OrderCancelled($notification, $name),
            default => null,
        };

        if ($event !== null) {
            $this->events->dispatch($event);
        }

        if ($ttl > 0) {
            $this->cache->put($key, true, $ttl);
        }

        return new Response('', 200);
    }

    /**
     * @throws InvalidNotification When "allowed_ips" is configured and the sender is not on the list.
     */
    private function checkSender(Request $request): void
    {
        $allowed = $this->settings->get('comfino.notifications.allowed_ips', []);
        $allowed = is_array($allowed) ? array_values(array_filter($allowed, is_string(...))) : [];

        if ($allowed === []) {
            return;
        }

        $ip = (string) $request->ip();

        if (! IpUtils::checkIp($ip, $allowed)) {
            throw InvalidNotification::forbiddenSender($ip);
        }
    }
}
