<!doctype html>
<html lang="ur" dir="ltr">
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Order Slip' }}</title>
    <style>
        @page {
            margin: 8mm 10mm 8mm 10mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            color: #123E4A;
            font-size: 10px;
            line-height: 1.35;
            margin: 0;
            padding: 0;
            background: #ffffff;
        }
        .page-wrapper {
            width: 100%;
            page-break-inside: avoid;
        }
        .w-full {
            width: 100%;
        }
        table {
            border-collapse: collapse;
            border-spacing: 0;
        }

        /* ── Top Header ────────────────────────────────────────── */
        .header-grid {
            width: 100%;
            margin-bottom: 12px;
        }
        .header-grid td {
            vertical-align: middle;
        }
        .qr-card {
            border: 1.5px solid #85B5C2;
            border-radius: 6px;
            background: #FFFFFF;
            padding: 6px 4px 4px;
            text-align: center;
            width: 95px;
        }
        .qr-ref {
            font-size: 11px;
            font-weight: bold;
            color: #08697A;
            margin-top: 3px;
        }
        .qr-qty {
            font-size: 10px;
            font-weight: bold;
            color: #2D6170;
        }

        .title-card {
            text-align: center;
            padding: 0 10px;
        }
        .studio-name {
            font-size: 28px;
            font-weight: bold;
            color: #08697A;
            margin: 0;
            line-height: 1.1;
        }
        .studio-slogan {
            font-size: 10.5px;
            color: #2D6170;
            margin: 4px 0 8px;
        }
        .badge-pill {
            display: inline-block;
            border: 1.8px solid #C49746;
            background: #FFFAF5;
            border-radius: 18px;
            padding: 3px 30px;
            font-size: 22px;
            font-weight: bold;
            color: #08697A;
            min-width: 150px;
            text-align: center;
        }
        .badge-decor {
            color: #C49746;
            font-size: 15px;
            margin: 0 8px;
        }

        .customer-card {
            border: 1.5px solid #85B5C2;
            border-radius: 6px;
            background: #F4F9FB;
            padding: 10px 12px;
            width: 200px;
        }
        .customer-table {
            width: 100%;
        }
        .customer-table td {
            padding: 4px 0;
            font-size: 10.5px;
        }
        .cust-lbl {
            font-weight: bold;
            color: #08697A;
            width: 60px;
            text-align: right;
        }
        .cust-val {
            color: #123E4A;
            text-align: right;
            font-weight: 500;
        }

        /* ── Main Two Columns ──────────────────────────────────── */
        .columns-grid {
            width: 100%;
            margin-bottom: 12px;
        }
        .columns-grid > tbody > tr > td {
            vertical-align: top;
        }

        .service-table {
            width: 100%;
            border: 1.5px solid #08697A;
            border-radius: 6px;
        }
        .banner-row th {
            background: #08697A;
            color: #FFFFFF;
            padding: 5px 8px;
            border-bottom: 1.5px solid #08697A;
        }
        .banner-inner {
            width: 100%;
        }
        .banner-inner td {
            vertical-align: middle;
            color: #FFFFFF;
        }
        .circle-num {
            background: #E67E22;
            color: #FFFFFF;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            line-height: 24px;
            text-align: center;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
        }
        .banner-text {
            font-size: 12.5px;
            font-weight: bold;
            color: #FFFFFF;
            padding-right: 8px;
            text-align: right;
        }

        .subhead-row th {
            background: #E3EFF3;
            color: #08697A;
            font-size: 10px;
            font-weight: bold;
            padding: 6px 4px;
            border-bottom: 1.2px solid #85B5C2;
            border-right: 0.8px solid #BED8E0;
            text-align: center;
        }
        .subhead-row th:last-child {
            border-right: none;
        }

        .service-table tbody td {
            border-bottom: 0.8px solid #CFE2E8;
            border-right: 0.8px solid #CFE2E8;
            vertical-align: middle;
        }
        .service-table tbody td:last-child {
            border-right: none;
        }
        .service-table tbody tr:last-child td {
            border-bottom: none;
        }

        .name-cell {
            text-align: right;
            font-weight: bold;
            color: #123E4A;
            padding-right: 8px;
        }
        .num-cell {
            text-align: center;
            font-weight: bold;
            color: #08697A;
        }
        .unit-cell {
            text-align: center;
            color: #2D6170;
            font-weight: 500;
        }

        .val-box {
            border: 1.2px solid #99C7D4;
            background: #FFFFFF;
            border-radius: 4px;
            text-align: center;
            font-weight: bold;
            color: #08697A;
            width: 100%;
        }
        .note-box {
            border: 1px solid #B4D7DF;
            background: #FAFDFF;
            border-radius: 4px;
            text-align: center;
            color: #2D6170;
            width: 100%;
        }

        .choice-cell {
            text-align: center;
            white-space: nowrap;
        }
        .radio-item {
            font-size: 9.5px;
            font-weight: 500;
        }
        .radio-bullet {
            font-size: 12px;
            color: #08697A;
        }
        .radio-bullet.active {
            color: #E67E22;
            font-weight: bold;
        }

        /* ── Bottom Section ────────────────────────────────────── */
        .bottom-grid {
            width: 100%;
            margin-bottom: 10px;
        }
        .bottom-grid > tbody > tr > td {
            vertical-align: top;
        }

        .card-box {
            border: 1.5px solid #D7A762;
            background: #FFFDF9;
            border-radius: 6px;
            padding: 10px 12px;
        }
        .card-header {
            font-size: 12px;
            font-weight: bold;
            color: #08697A;
            margin-bottom: 8px;
            border-bottom: 1.2px solid #F0D9B7;
            padding-bottom: 5px;
            text-align: right;
        }

        .ruled-row {
            border-bottom: 1px solid #85B8C7;
            color: #123E4A;
            font-size: 10px;
            padding: 0 4px;
            text-align: right;
        }
        .notes-pill {
            display: inline-block;
            background: #F8E5CB;
            color: #7A450A;
            font-size: 9px;
            font-weight: bold;
            padding: 3px 14px;
            border-radius: 12px;
            margin-top: 8px;
            text-align: center;
        }

        .details-table {
            width: 100%;
        }
        .details-table td {
            border-bottom: 0.8px solid #F5E5D0;
            font-size: 10.5px;
        }
        .details-table tr:last-child td {
            border-bottom: none;
        }
        .det-lbl {
            font-weight: bold;
            color: #08697A;
            text-align: right;
            width: 120px;
        }
        .det-val {
            color: #123E4A;
            text-align: right;
            font-weight: 500;
        }

        /* ── Footer ────────────────────────────────────────────── */
        .footer-grid {
            width: 100%;
            border-top: 1px solid #D9E5E9;
            padding-top: 6px;
            font-size: 9px;
            color: #6E8A92;
        }
    </style>
</head>
<body>

@foreach($pages as $pIndex => $p)
@php
    $rowCount = max(count($p['col1_rows']), count($p['col2_rows']));
    if ($rowCount <= 7) {
        $cellPad = '12px 6px';
        $valHeight = '32px';
        $fSize = '11.5px';
        $ruledCount = 7;
        $ruledHeight = '24px';
        $detPad = '14px 4px';
    } elseif ($rowCount <= 11) {
        $cellPad = '7.5px 5px';
        $valHeight = '24px';
        $fSize = '10.5px';
        $ruledCount = 6;
        $ruledHeight = '22px';
        $detPad = '11px 4px';
    } else {
        $cellPad = '3.5px 3px';
        $valHeight = '18px';
        $fSize = '9px';
        $ruledCount = 4;
        $ruledHeight = '18px';
        $detPad = '6px 4px';
    }
@endphp
<div class="page-wrapper">

    <!-- Top Header: LTR layout (QR on Left, Studio in Center, Customer on Right) -->
    <table class="header-grid">
        <tr>
            <!-- Left: QR Code -->
            <td style="width: 100px; text-align: left;">
                <div class="qr-card">
                    <barcode code="{{ $p['order_reference'] }}" type="QR" size="0.95" error="M" disableborder="1" />
                    <div class="qr-ref">{{ $p['order_reference'] }}</div>
                    <div class="qr-qty">Qty: {{ $p['quantity'] }}</div>
                </div>
            </td>

            <!-- Center: Studio Title & Service Pill -->
            <td class="title-card">
                <div class="studio-name">سلائی اسٹوڈیو</div>
                <div class="studio-slogan">- معیاری سلائی • بہترین فٹنگ • آپ کا اعتماد -</div>
                <div>
                    <span class="badge-pill">
                        <span class="badge-decor">◆</span>
                        {{ $p['service_name'] }}
                        <span class="badge-decor">◆</span>
                    </span>
                </div>
            </td>

            <!-- Right: Customer Info Box -->
            <td style="width: 205px; text-align: right;">
                <div class="customer-card" dir="rtl">
                    <table class="customer-table">
                        <tr>
                            <td class="cust-lbl">نام:</td>
                            <td class="cust-val">{{ $p['customer_name'] ?: '—' }}</td>
                        </tr>
                        <tr>
                            <td class="cust-lbl">فون نمبر:</td>
                            <td class="cust-val" dir="ltr">{{ $p['customer_phone'] ?: '—' }}</td>
                        </tr>
                        <tr>
                            <td class="cust-lbl">تاریخ:</td>
                            <td class="cust-val">{{ $p['date'] }}</td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <!-- Middle: Two Columns side by side (Col 1 on Left, Col 2 on Right) -->
    <table class="columns-grid">
        <tr>
            <!-- Left Column: Column 1 (بنیادی ناپ) -->
            <td style="width: 49.5%; padding-right: 6px;">
                <table class="service-table" dir="rtl">
                    <thead>
                        <tr class="banner-row">
                            <th colspan="4">
                                <table class="banner-inner" dir="ltr">
                                    <tr>
                                        <td style="width: 28px; text-align: left;">
                                            <div class="circle-num">1</div>
                                        </td>
                                        <td class="banner-text">
                                            {{ $p['col1_title'] }}
                                        </td>
                                    </tr>
                                </table>
                            </th>
                        </tr>
                        <tr class="subhead-row">
                            <th style="width: 14%;">اکائی</th>
                            <th style="width: 28%;">قدر</th>
                            <th style="width: 46%;">اردو نام</th>
                            <th style="width: 12%;">نمبر</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($p['col1_rows'] as $r)
                        <tr>
                            <td class="unit-cell" style="padding: {{ $cellPad }}; font-size: {{ $fSize }};">
                                {{ $r['unit'] ?: 'انچ' }}
                            </td>
                            <td style="padding: {{ $cellPad }};">
                                <div class="val-box" style="height: {{ $valHeight }}; line-height: {{ $valHeight }}; font-size: {{ $fSize }};">
                                    {{ $r['val'] !== '' ? $r['val'] : '' }}
                                </div>
                            </td>
                            <td class="name-cell" style="padding: {{ $cellPad }}; font-size: {{ $fSize }};">
                                {{ $r['name'] }}
                            </td>
                            <td class="num-cell" style="padding: {{ $cellPad }}; font-size: {{ $fSize }};">
                                {{ $r['num'] }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </td>

            <!-- Right Column: Column 2 (اسٹائل اور ڈیزائن) -->
            <td style="width: 49.5%; padding-left: 6px;">
                <table class="service-table" dir="rtl">
                    <thead>
                        <tr class="banner-row">
                            <th colspan="4">
                                <table class="banner-inner" dir="ltr">
                                    <tr>
                                        <td style="width: 28px; text-align: left;">
                                            <div class="circle-num">2</div>
                                        </td>
                                        <td class="banner-text">
                                            {{ $p['col2_title'] }}
                                        </td>
                                    </tr>
                                </table>
                            </th>
                        </tr>
                        <tr class="subhead-row">
                            <th style="width: 18%;">نوٹ</th>
                            <th style="width: 28%;">انتخاب</th>
                            <th style="width: 44%;">اسٹائل کا نام</th>
                            <th style="width: 10%;">نمبر</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($p['col2_rows'] as $r)
                        <tr>
                            <td style="padding: {{ $cellPad }};">
                                <div class="note-box" style="min-height: {{ $valHeight }}; line-height: {{ $valHeight }}; font-size: {{ $fSize }};">
                                    {{ $r['note'] !== '' ? $r['note'] : '' }}
                                </div>
                            </td>
                            <td class="choice-cell" style="padding: {{ $cellPad }}; font-size: {{ $fSize }};">
                                @php
                                    $val = strtolower((string)$r['choice']);
                                    $isYes = in_array($val, ['yes', '1', 'true', 'ہاں'], true);
                                    $isNo = in_array($val, ['no', '0', 'false', 'نہیں'], true);
                                @endphp
                                <span class="radio-item">
                                    <span class="radio-bullet {{ $isYes ? 'active' : '' }}">{{ $isYes ? '&#9679;' : '&#9675;' }}</span> ہاں
                                </span>
                                &nbsp;
                                <span class="radio-item">
                                    <span class="radio-bullet {{ $isNo ? 'active' : '' }}">{{ $isNo ? '&#9679;' : '&#9675;' }}</span> نہیں
                                </span>
                            </td>
                            <td class="name-cell" style="padding: {{ $cellPad }}; font-size: {{ $fSize }};">
                                {{ $r['name'] }}
                            </td>
                            <td class="num-cell" style="padding: {{ $cellPad }}; font-size: {{ $fSize }};">
                                {{ $r['num'] }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <!-- Bottom: Notes on Left, Order Details on Right -->
    <table class="bottom-grid">
        <tr>
            <!-- Left: Notes / Special Instructions -->
            <td style="width: 58%; padding-right: 6px;">
                <div class="card-box" dir="rtl">
                    <div class="card-header">
                        نوٹ / خصوصی ہدایات
                    </div>
                    @php
                        $noteLines = array_filter(explode("\n", (string)($p['notes'] ?? '')));
                        $noteLines = array_values($noteLines);
                    @endphp
                    @for($i = 0; $i < $ruledCount; $i++)
                        <div class="ruled-row" style="height: {{ $ruledHeight }}; line-height: {{ $ruledHeight }};">{{ $noteLines[$i] ?? '' }}</div>
                    @endfor
                    @if(!empty($p['notes_badge']))
                    <div style="text-align: center;">
                        <span class="notes-pill">{{ $p['notes_badge'] }}</span>
                    </div>
                    @endif
                </div>
            </td>

            <!-- Right: Order Details -->
            <td style="width: 42%; padding-left: 6px;">
                <div class="card-box" dir="rtl">
                    <div class="card-header">
                        آرڈر کی تفصیلات
                    </div>
                    <table class="details-table">
                        <tr>
                            <td class="det-lbl" style="padding: {{ $detPad }};">وعدہ شدہ ڈیلیوری:</td>
                            <td class="det-val" style="padding: {{ $detPad }};">{{ $p['delivery_date'] }}</td>
                        </tr>
                        <tr>
                            <td class="det-lbl" style="padding: {{ $detPad }};">حالت:</td>
                            <td class="det-val" style="padding: {{ $detPad }};">{{ $p['status'] }}</td>
                        </tr>
                        <tr>
                            <td class="det-lbl" style="padding: {{ $detPad }};">شعبہ:</td>
                            <td class="det-val" style="padding: {{ $detPad }};">{{ $p['department'] }}</td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <!-- Footer -->
    <table class="footer-grid">
        <tr>
            <td style="text-align: left; font-weight: bold; color: #08697A;">
                {{ $p['order_reference'] }}
            </td>
            <td style="text-align: right;">
                Printed: {{ $p['printed_at'] }}
            </td>
        </tr>
    </table>

</div>

@if(!$loop->last)
    <pagebreak />
@endif
@endforeach

</body>
</html>
