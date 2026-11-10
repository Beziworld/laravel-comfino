<?php

declare(strict_types=1);

namespace Beziworld\Comfino\Enums;

/**
 * Financial product types of the ComfinoPay dictionary.
 *
 * The availability of each type depends on the configuration of the shop
 * account. PAY_IN_PARTS and INSTANT_PAYMENTS come from the dictionary of the
 * official client.
 */
enum LoanType: string
{
    case InstallmentsZeroPercent = 'INSTALLMENTS_ZERO_PERCENT';
    case ConvenientInstallments = 'CONVENIENT_INSTALLMENTS';
    case PayLater = 'PAY_LATER';
    case CompanyInstallments = 'COMPANY_INSTALLMENTS';
    case CompanyBnpl = 'COMPANY_BNPL';
    case RenewableLimit = 'RENEWABLE_LIMIT';
    case Blik = 'BLIK';
    case Leasing = 'LEASING';
    case PayInParts = 'PAY_IN_PARTS';
    case InstantPayments = 'INSTANT_PAYMENTS';

    public function label(): string
    {
        return match ($this) {
            self::InstallmentsZeroPercent => 'Raty 0%',
            self::ConvenientInstallments => 'Niskie raty',
            self::PayLater => 'Kup teraz, zapłać później',
            self::CompanyInstallments => 'Kredyt dla firm',
            self::CompanyBnpl => 'Odroczenie płatności faktury',
            self::RenewableLimit => 'Limit odnawialny',
            self::Blik => 'Płatność BLIK',
            self::Leasing => 'Leasing',
            self::PayInParts => 'Podziel na 3',
            self::InstantPayments => 'Płatności natychmiastowe',
        };
    }

    /**
     * Normalises enum cases and raw dictionary strings to API values.
     *
     * @param iterable<self|string> $types
     * @return list<string>
     */
    public static function values(iterable $types): array
    {
        $values = [];

        foreach ($types as $type) {
            $values[] = $type instanceof self ? $type->value : strtoupper(trim($type));
        }

        return array_values(array_unique($values));
    }
}
