<?php

declare(strict_types=1);

namespace Beziworld\Comfino\Facades;

use Beziworld\Comfino\ComfinoManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Beziworld\Comfino\ComfinoClient connection(?string $name = null)
 * @method static \Beziworld\Comfino\ComfinoClient using(\Beziworld\Comfino\ComfinoConfig|array<string, mixed> $config)
 * @method static void resolveConnectionsUsing(\Closure(string): (\Beziworld\Comfino\ComfinoConfig|array<string, mixed>|null) $resolver)
 * @method static string getDefaultConnection()
 * @method static list<string> connectionNames()
 * @method static void purge(?string $name = null)
 * @method static \Beziworld\Comfino\ComfinoConfig config()
 * @method static ?string connectionName()
 * @method static ?string notifyUrl()
 * @method static list<\Beziworld\Comfino\Data\FinancialProduct> financialProducts(\Beziworld\Comfino\Data\ProductQuery|int $query)
 * @method static list<\Beziworld\Comfino\Data\FinancialProduct> financialProductsForCart(\Beziworld\Comfino\Data\Cart $cart, \Beziworld\Comfino\Data\ProductQuery|int|null $query = null)
 * @method static \Beziworld\Comfino\Data\LoanOption bestOffer(int $loanAmount)
 * @method static \Beziworld\Comfino\Data\CreatedOrder createOrder(\Beziworld\Comfino\Data\OrderRequest $order)
 * @method static array<array-key, mixed> validateOrder(\Beziworld\Comfino\Data\OrderRequest $order)
 * @method static \Beziworld\Comfino\Data\Order order(string $orderId)
 * @method static \Beziworld\Comfino\Enums\OrderStatus|null cancelOrder(string $orderId)
 * @method static list<\Beziworld\Comfino\Data\ProductType> productTypes(\Beziworld\Comfino\Enums\ProductListType $listType = \Beziworld\Comfino\Enums\ProductListType::Widget)
 * @method static array<string, string> widgetTypes()
 * @method static array<string, list<string>> creditors()
 * @method static bool isAccountActive()
 * @method static string widgetKey()
 * @method static string fetchWidgetKey()
 * @method static \Beziworld\Comfino\Support\Widget widget(?string $widgetKey = null)
 * @method static string paywallAuthKey(?int $timestamp = null)
 * @method static bool verifySignature(string $rawBody, ?string $signature)
 * @method static \Beziworld\Comfino\Data\Notification parseNotification(string $rawBody, ?string $signature, bool $verifySignature = true)
 *
 * @see \Beziworld\Comfino\ComfinoManager
 * @see \Beziworld\Comfino\ComfinoClient
 */
final class Comfino extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ComfinoManager::class;
    }
}
