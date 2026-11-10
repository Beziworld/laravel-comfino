<?php

declare(strict_types=1);

namespace Beziworld\Comfino\Console;

use Beziworld\Comfino\ComfinoManager;
use Beziworld\Comfino\Exceptions\ComfinoException;
use Illuminate\Console\Command;

/**
 * Checks the API key of a connection: account activation, widget key and enabled product types.
 */
final class StatusCommand extends Command
{
    /** @var string */
    protected $signature = 'comfino:status
        {--connection= : Connection name from config/comfino.php}
        {--amount= : Also list offers for this amount in PLN, e.g. 1500}';

    /** @var string */
    protected $description = 'Check the ComfinoPay API key, account activation and available financial products';

    public function handle(ComfinoManager $manager): int
    {
        $connection = $this->input->getOption('connection');
        $amount = $this->input->getOption('amount');

        try {
            $client = $manager->connection(is_string($connection) && $connection !== '' ? $connection : null);
            $config = $client->config();

            $this->line(sprintf('Connection: %s (%s, %s)', $config->name ?? 'runtime', $config->sandbox ? 'sandbox' : 'production', $config->apiUrl()));
            $this->line('Account active: ' . ($client->isAccountActive() ? 'yes' : 'no'));
            $this->line('Widget key: ' . $client->widgetKey());

            $rows = [];

            foreach ($client->productTypes() as $type) {
                $rows[] = [$type->code, $type->name, $type->publicName];
            }

            $this->table(['Type', 'Name', 'Public name'], $rows);

            if (is_string($amount) && is_numeric($amount)) {
                $offers = [];

                foreach ($client->financialProducts((int) round((float) $amount * 100)) as $product) {
                    $option = $product->defaultOption;
                    $offers[] = [$product->name, $product->typeValue, $option->term, $option->instalment->decimal(), $option->toPay->decimal(), $option->rrsoPercent() ?? '-'];
                }

                $this->table(['Product', 'Type', 'Term', 'Instalment', 'To pay', 'RRSO %'], $offers);
            }
        } catch (ComfinoException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
