<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Faktur {{ $nomorFaktur }}</title>
    <style>
        @php
            $isThird = ($paperSize ?? 'a4') === 'third_a4';
            $isHalf  = ($paperSize ?? 'a4') === 'half_a4';
            $isSmall = $isThird || $isHalf;
        @endphp
        * { font-family: DejaVu Sans, sans-serif; box-sizing: border-box; }
        body {
            margin: {{ $isThird ? '10px 14px' : ($isHalf ? '18px 20px' : '30px') }};
            color: #1f2937;
            font-size: {{ $isThird ? '9px' : ($isHalf ? '11px' : '13px') }};
        }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: {{ $isThird ? '1px' : '2px' }} solid #111827; padding-bottom: {{ $isThird ? '6px' : ($isHalf ? '9px' : '12px') }}; }
        .header-left { display: flex; align-items: center; gap: {{ $isThird ? '6px' : '14px' }}; }
        .logo { height: {{ $isThird ? '28px' : ($isHalf ? '42px' : '60px') }}; width: auto; max-width: {{ $isThird ? '80px' : ($isHalf ? '130px' : '180px') }}; }
        .title { font-size: {{ $isThird ? '11px' : ($isHalf ? '16px' : '22px') }}; font-weight: bold; }
        .nomor { font-size: {{ $isThird ? '7px' : ($isHalf ? '9px' : '12px') }}; color: #6b7280; }
        .row { margin-top: {{ $isThird ? '6px' : ($isHalf ? '12px' : '18px') }}; }
        .label { font-size: {{ $isThird ? '7px' : ($isHalf ? '9px' : '11px') }}; color: #6b7280; text-transform: uppercase; }
        .value { font-size: {{ $isThird ? '10px' : ($isHalf ? '13px' : '15px') }}; font-weight: 600; }
        .nominal-big { font-size: {{ $isThird ? '12px' : ($isHalf ? '17px' : '24px') }}; font-weight: bold; margin-top: 2px; }
        .terbilang { font-style: italic; color: #4b5563; margin-top: 2px; font-size: {{ $isThird ? '7px' : ($isHalf ? '9px' : '11px') }}; }
        .memo { margin-top: {{ $isThird ? '8px' : '14px' }}; padding: {{ $isThird ? '5px 8px' : '10px 14px' }}; background: #f9fafb; border-left: {{ $isThird ? '2px' : '4px' }} solid #111827; white-space: pre-wrap; font-size: {{ $isThird ? '7px' : ($isHalf ? '9px' : '11px') }}; }
        .footer { margin-top: {{ $isThird ? '12px' : ($isHalf ? '28px' : '50px') }}; display: flex; justify-content: space-between; font-size: {{ $isThird ? '7px' : ($isHalf ? '9px' : '12px') }}; color: #6b7280; }
        .signature { text-align: center; }
        .item-table { width: 100%; border-collapse: collapse; margin-top: {{ $isThird ? '6px' : ($isHalf ? '12px' : '20px') }}; }
        .item-table th { background: #f3f4f6; text-align: left; padding: {{ $isThird ? '4px 5px' : ($isHalf ? '5px 7px' : '8px 10px') }}; font-size: {{ $isThird ? '7px' : ($isHalf ? '9px' : '11px') }}; text-transform: uppercase; color: #6b7280; border-bottom: 1px solid #d1d5db; }
        .item-table td { padding: {{ $isThird ? '3px 5px' : ($isHalf ? '5px 7px' : '7px 10px') }}; font-size: {{ $isThird ? '8px' : ($isHalf ? '10px' : '13px') }}; border-bottom: 1px solid #e5e7eb; }
        .item-table .right { text-align: right; }
        .item-table .center { text-align: center; }
        .total-row td { font-weight: bold; font-size: {{ $isThird ? '9px' : ($isHalf ? '11px' : '15px') }}; border-top: 2px solid #111827; padding-top: {{ $isThird ? '4px' : '8px' }}; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            @if (! empty($logoDataUri))
                <img src="{{ $logoDataUri }}" alt="Logo" class="logo" />
            @endif
            <div>
                <div class="title">FAKTUR</div>
                <div class="nomor">{{ $nomorFaktur }}</div>
            </div>
        </div>
        <div style="text-align: right;">
            <div class="value">{{ $tanggal }}</div>
        </div>
    </div>

    <div class="row">
        <div class="label">Kepada Yth.</div>
        <div class="value">{{ $nama }}</div>
    </div>

    @if (! empty($items))
        <table class="item-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Deskripsi</th>
                    <th class="center">Qty</th>
                    <th class="right">Harga Satuan</th>
                    <th class="right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @php $no = 1; @endphp
                @foreach ($items as $item)
                    <tr>
                        <td>{{ $no++ }}</td>
                        <td>{{ $item['description'] }}</td>
                        <td class="center">{{ (int) $item['qty'] }}</td>
                        <td class="right">{{ number_format((float) $item['price'], 0, ',', '.') }}</td>
                        <td class="right">{{ number_format((float) $item['subtotal'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="4" class="right">TOTAL</td>
                    <td class="right">{{ $nominal }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    <div class="row">
        <div class="label">Jumlah Tagihan</div>
        <div class="nominal-big">{{ $nominal }}</div>
        <div class="terbilang">Terbilang: {{ $terbilang }}</div>
    </div>

    @if (! empty($memo))
        <div class="memo">{{ $memo }}</div>
    @endif

    <div class="footer">
        <div>Pembayaran dapat ditransfer ke rekening yang tertera pada catatan.</div>
        <div class="signature">
            Hormat kami,<br><br><br>
            ____________________
        </div>
    </div>
</body>
</html>
