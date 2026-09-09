{{--
     The seller's identity block, read from the general settings rather than
     written into each document. Turkish consumer law requires the seller's
     registered title, address, telephone, tax office and tax number to appear
     on the distance sales contract and the pre-contractual form; the same
     block also answers the "who is the controller" question GDPR asks.

     Fields the law requires are always rendered, showing an em dash when the
     operator has not filled them in — a contract that quietly drops the
     seller's address looks complete and is not. Optional fields (MERSIS,
     trade registry) are shown only once they hold a value.
--}}
@php
    // Bu parça üç ayrı yerden çağrılıyor (yasal belgeler, hakkımızda, ileride
    // başkaları). Birinin göndermeyi unuttuğu bir anahtar sayfayı düşürmesin;
    // o satır boş görünsün yeter.
    $company = array_merge([
        'name' => '', 'legal_name' => '', 'address' => '', 'city' => '', 'state' => '',
        'postcode' => '', 'country' => '', 'phone' => '', 'email' => '', 'website' => '',
        'tax_office' => '', 'tax_id' => '', 'mersis' => '', 'trade_registry' => '',
    ], $company);

    $mandatory = [
        ($tr ? 'Unvan'                    : 'Registered name')  => $company['legal_name'] ?: $company['name'],
        ($tr ? 'Marka / işletme adı'      : 'Trading as')       => ($company['legal_name'] && $company['legal_name'] !== $company['name']) ? $company['name'] : '',
        ($tr ? 'Adres'                    : 'Address')          => trim(implode(', ', array_filter([
                                                                       $company['address'], $company['postcode'],
                                                                       $company['city'], $company['state'], $company['country'],
                                                                   ]))),
        ($tr ? 'Telefon'                  : 'Telephone')        => $company['phone'],
        ($tr ? 'E-posta'                  : 'Email')            => $company['email'],
        ($tr ? 'İnternet adresi'          : 'Website')          => $company['website'],
        ($tr ? 'Vergi dairesi'            : 'Tax office')       => $company['tax_office'],
        ($tr ? 'Vergi numarası'           : 'Tax number')       => $company['tax_id'],
    ];

    // Shown only when set: not every company has these, and an empty row
    // invites the question "why is that blank?" where none is warranted.
    $optional = [
        ($tr ? 'MERSİS numarası'          : 'MERSIS number')    => $company['mersis'],
        ($tr ? 'Ticaret sicil numarası'   : 'Trade registry no') => $company['trade_registry'],
    ];

    $rows = $mandatory;
    // "Marka" only earns a row when the brand and the registered name differ.
    if ($rows[$tr ? 'Marka / işletme adı' : 'Trading as'] === '') {
        unset($rows[$tr ? 'Marka / işletme adı' : 'Trading as']);
    }
    foreach ($optional as $label => $value) {
        if (trim((string) $value) !== '') {
            $rows[$label] = $value;
        }
    }
@endphp
<table>
    <tbody>
    @foreach($rows as $label => $value)
        <tr>
            <th style="width:32%;">{{ $label }}</th>
            <td>{{ trim((string) $value) !== '' ? $value : '—' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
