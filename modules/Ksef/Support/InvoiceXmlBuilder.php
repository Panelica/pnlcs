<?php

namespace Modules\Ksef\Support;

use App\Models\Invoice;
use App\Models\Setting;

/**
 * Builds a minimal but valid FA(2) invoice XML for KSeF 2.0.
 *
 * Follows schemat_FA(2)_v1-0E.xsd (targetNamespace
 * http://crd.gov.pl/wzor/2023/06/29/12648/).
 */
class InvoiceXmlBuilder
{
    private const NS = 'http://crd.gov.pl/wzor/2023/06/29/12648/';

    /**
     * @return string FA(2) XML document
     */
    public function build(Invoice $invoice, string $sellerNip): string
    {
        $invoice = $invoice->load('items');

        $sellerName = $this->sellerName();
        $currency = $this->currency($invoice);

        $lines = '';
        $totals = $this->totals($invoice, $lines);
        $issueDate = ($invoice->date ?? now())->format('Y-m-d');

        $buyerNip = $invoice->buyer('tax_id');

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Faktura xmlns="'.self::NS.'" xmlns:etd="http://crd.gov.pl/xml/schematy/dziedzinowe/mf/2022/01/05/eD/DefinicjeTypy/">'
            .$this->naglowek()
            .$this->podmiot1($sellerNip, $sellerName)
            .$this->podmiot2($invoice, $buyerNip)
            .'<Fa>'
            .'<KodWaluty>'.e($currency).'</KodWaluty>'
            .'<P_1>'.$issueDate.'</P_1>'
            .'<P_2>'.e((string) ($invoice->invoice_num ?? $invoice->id)).'</P_2>'
            .$totals
            .$this->adnotacje()
            .'<RodzajFaktury>VAT</RodzajFaktury>'
            .$lines
            .$this->platnosc($invoice)
            .'</Fa>'
            .'</Faktura>';
    }

    private function naglowek(): string
    {
        return '<Naglowek>'
            .'<KodFormularza kodSystemowy="FA (2)" wersjaSchemy="1-0E">FA</KodFormularza>'
            .'<WariantFormularza>2</WariantFormularza>'
            .'<DataWytworzeniaFa>'.gmdate('Y-m-d\TH:i:s\Z').'</DataWytworzeniaFa>'
            .'<SystemInfo>PNLCS</SystemInfo>'
            .'</Naglowek>';
    }

    private function podmiot1(string $nip, string $name): string
    {
        return '<Podmiot1>'
            .'<DaneIdentyfikacyjne>'
            .'<NIP>'.e($nip).'</NIP>'
            .'<Nazwa>'.e($name).'</Nazwa>'
            .'</DaneIdentyfikacyjne>'
            .'<Adres><KodKraju>PL</KodKraju><AdresL1>'.e($this->sellerAddress()).'</AdresL1></Adres>'
            .'</Podmiot1>';
    }

    private function podmiot2(Invoice $invoice, ?string $nip): string
    {
        $id = $nip
            ? '<NIP>'.e($nip).'</NIP>'
            : '<BrakID>1</BrakID>';

        $name = trim((string) ($invoice->buyer('company_name') ?: $invoice->buyer('first_name').' '.$invoice->buyer('last_name')));

        $address = '';
        $country = (string) ($invoice->buyer('country') ?: 'PL');
        $parts = array_filter([
            trim((string) ($invoice->buyer('address1') ?: '')),
            trim((string) ($invoice->buyer('postcode') ?: '')),
            trim((string) ($invoice->buyer('city') ?: '')),
        ]);
        $line = implode(' ', $parts);
        if ($line !== '') {
            $address = '<Adres><KodKraju>'.e($country).'</KodKraju><AdresL1>'.e($line).'</AdresL1></Adres>';
        }

        return '<Podmiot2>'
            .'<DaneIdentyfikacyjne>'
            .$id
            .($name !== '' ? '<Nazwa>'.e($name).'</Nazwa>' : '')
            .'</DaneIdentyfikacyjne>'
            .$address
            .'</Podmiot2>';
    }

    private function adnotacje(): string
    {
        return '<Adnotacje>'
            .'<P_16>2</P_16>'
            .'<P_17>2</P_17>'
            .'<P_18>2</P_18>'
            .'<P_18A>2</P_18A>'
            .'<Zwolnienie><P_19N>1</P_19N></Zwolnienie>'
            .'<NoweSrodkiTransportu><P_22N>1</P_22N></NoweSrodkiTransportu>'
            .'<P_23>2</P_23>'
            .'<PMarzy><P_PMarzyN>1</P_PMarzyN></PMarzy>'
            .'</Adnotacje>';
    }

    private function platnosc(Invoice $invoice): string
    {
        $due = ($invoice->due_date ?? now())->format('Y-m-d');

        return '<Platnosc>'
            .'<TerminPlatnosci><Termin>'.$due.'</Termin></TerminPlatnosci>'
            .'<FormaPlatnosci>6</FormaPlatnosci>'
            .'</Platnosc>';
    }

    /**
     * Group line items by VAT rate and produce P_13_x/P_14_x totals plus the
     * FaWiersz rows.
     */
    private function totals(Invoice $invoice, string &$lines): string
    {
        $groups = [];
        $rows = '';
        $n = 1;
        $grand = 0.0;

        foreach ($invoice->items as $item) {
            $qty = max(1, (int) ($item->qty ?? 1));
            $net = round((float) $item->amount * $qty, 2);
            $rate = $item->tax_rate !== null ? (float) $item->tax_rate : ($item->taxed ? (float) $invoice->tax_rate : 0.0);
            $vat = round($net * $rate / 100, 2);
            $grand += $net + $vat;

            $key = (string) $rate;
            if (! isset($groups[$key])) {
                $groups[$key] = ['rate' => $rate, 'net' => 0.0, 'vat' => 0.0];
            }
            $groups[$key]['net'] += $net;
            $groups[$key]['vat'] += $vat;

            $rows .= '<FaWiersz>'
                .'<NrWierszaFa>'.$n.'</NrWierszaFa>'
                .'<P_7>'.e((string) ($item->description ?? '')).'</P_7>'
                .'<P_8A>'.e((string) ($item->unit ?? 'szt.')).'</P_8A>'
                .'<P_8B>'.$this->qty($qty).'</P_8B>'
                .'<P_9A>'.$this->amount($item->amount).'</P_9A>'
                .'<P_11>'.$this->amount($net).'</P_11>'
                .'<P_11Vat>'.$this->amount($vat).'</P_11Vat>'
                .'<P_12>'.$this->rateCode($rate).'</P_12>'
                .'</FaWiersz>';
            $n++;
        }

        $lines = $rows;

        $totals = '';
        foreach ($groups as $group) {
            [$netField, $vatField] = $this->rateFields($group['rate']);
            if ($netField === null) {
                continue;
            }
            $totals .= '<'.$netField.'>'.$this->amount($group['net']).'</'.$netField.'>';
            if ($vatField !== null) {
                $totals .= '<'.$vatField.'>'.$this->amount($group['vat']).'</'.$vatField.'>';
            }
        }

        $totals .= '<P_15>'.$this->amount($grand).'</P_15>';

        return $totals;
    }

    /** @return array{0: ?string, 1: ?string} [net field, vat field] */
    private function rateFields(float $rate): array
    {
        if ($rate >= 22) {
            return ['P_13_1', 'P_14_1'];
        }
        if ($rate >= 7) {
            return ['P_13_2', 'P_14_2'];
        }
        if ($rate >= 4) {
            return ['P_13_3', 'P_14_3'];
        }
        if ($rate > 0) {
            return ['P_13_5', 'P_14_5'];
        }

        return ['P_13_6_1', null];
    }

    private function rateCode(float $rate): string
    {
        if ($rate <= 0) {
            return '0';
        }

        return rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.');
    }

    private function amount(float|string $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function qty(int $value): string
    {
        return number_format((float) $value, 0, '.', '');
    }

    private function sellerName(): string
    {
        try {
            $name = trim((string) Setting::get('whitelabel_company_name', ''))
                ?: trim((string) Setting::get('CompanyName', ''));
        } catch (\Throwable) {
            $name = '';
        }

        return $name !== '' ? $name : (string) config('app.name', 'PNLCS');
    }

    private function sellerAddress(): string
    {
        try {
            $parts = array_filter([
                trim((string) Setting::get('Address1', '')),
                trim((string) Setting::get('Address2', '')),
            ]);
            $line = implode(' ', $parts);
        } catch (\Throwable) {
            $line = '';
        }

        return $line !== '' ? $line : 'Polska';
    }

    private function currency(Invoice $invoice): string
    {
        $client = $invoice->client;

        return $client?->currency?->code ?? 'PLN';
    }
}
