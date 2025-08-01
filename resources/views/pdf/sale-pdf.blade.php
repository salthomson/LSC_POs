<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "//www.w3.org/TR/html4/strict.dtd">
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
    <title>{{ __('messages.sale_pdf') }}</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon.ico') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="{{ asset('assets/css/bootstrap.min.css') }}" rel="stylesheet" type="text/css"/>
    <style>
        * {
            font-family: DejaVu Sans, Arial, "Helvetica", Arial, "Liberation Sans", sans-serif;
        }

        /* ----- POS PRINTER STYLES START ----- */
        body {
            font-family: monospace;
            font-size: 10px;
            width: 58mm; /* Standard width for a POS-58 printer */
            margin: 0;
            padding: 0;
        }

        table {
            max-width: 58mm;
            width: 100%;
        }
        
        .fw-bold {
            font-weight: 700 !important;
            color: #000 !important;
        }
        
        .fw-light {
            font-weight: 400 !important;
            color: #000 !important;
        }

        img {
            max-width: 100%;
            height: auto;
        }
        
        /* Hide all non-essential elements for printing */
        .btn, .header, .footer, .vi-bold-text, .vi-light-text, [align="right"] {
            display: none !important;
        }

        /* Center all content for a clean receipt look */
        body, table {
            text-align: center;
        }
        /* ----- POS PRINTER STYLES END ----- */


        @if(getLoginUserLanguage() !='ar')
            .fw-bold {
            font-weight: 500;
            color: #333;
        }

        @else
        .fw-bold {
            /*font-weight: 500;*/
            color: #333;
        }

        @endif

        @if(getLoginUserLanguage() =='vi')
            .vi-bold-text {
                font-size: 14px;
                font-weight: bolder;
                color: #333;
            }

            .vi-light-text {
                font-size: 16px;
            }
        @endif

        .fw-light {
            font-weight: 500;
            color: grey;
        }
    </style>

</head>
<body>

<table width="100%">
    <tr>
        <td align="center" style="vertical-align: bottom">
             <img src="{{$companyLogo}}" alt="Company Logo" width="80px">
        </td>
    </tr>
    <tr>
        <td align="center" style="vertical-align: bottom">
             <h2 style="color: black;">{{ getSettingValue('company_name') }}</h2>
        </td>
    </tr>
    <tr>
        <td align="center" style="vertical-align: bottom">
             <h4 class="fw-bold vi-bold-text">{{ getLoginUserLanguage() == 'cn' ? 'Address' : __('messages.pdf.address') }}: <span
                                    class="fw-light vi-light-text">{{ getSettingValue('address') }}</span></h4>
        </td>
    </tr>
    <tr>
        <td align="center" style="vertical-align: bottom">
             <h4 class="fw-bold vi-bold-text">{{ getLoginUserLanguage() == 'cn' ? 'Phone' : __('messages.pdf.phone') }}: <span
                                    class="fw-light vi-light-text">{{ getSettingValue('phone') }}</span></h4>
        </td>
    </tr>
    <tr>
        <td align="center" style="vertical-align: bottom">
             <h4 class="fw-bold vi-bold-text">{{ getLoginUserLanguage() == 'cn' ? 'Email' : __('messages.pdf.email') }}: <span
                                    class="fw-light vi-light-text">{{ getSettingValue('email') }}</span></h4>
        </td>
    </tr>
    <tr>
        <td align="center" style="vertical-align: bottom">
             <h3 style="color: black;">{{ $sale->reference_code }}</h3>
        </td>
    </tr>
</table>
<hr>
<table width="100%" style="margin-top: 40px;">
    <tr style="vertical-align: top;">
        <td style="width: 50%;">
            <table width="95%" cellpadding="0">
                <tr style="background-color: transparent;">
                    <td style="padding: 10px;font-size: 14px;">{{ getLoginUserLanguage() == 'cn' ? 'Customer' : 'Customer' }}</td>
                </tr>
                <tr style="background-color: transparent;">
                    <td style="padding: 10px;">
                        <p class="fw-bold vi-bold-text">Name: <span
                                    class="fw-light vi-light-text">{{ isset($sale->customer->name) ? $sale->customer->name : 'N/A' }}</span>
                        </p>
                        <p class="fw-bold vi-bold-text">Phone: <span
                                    class="fw-light vi-light-text">{{ isset($sale->customer->phone) ? $sale->customer->phone : 'N/A' }}</span>
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
<hr>
<table width="100%" cellspacing="0" cellpadding="10" style="margin-top: 40px;">
    <thead>
    <tr style="background-color: transparent; text-align: left;">
        <th style="color: #000; font-size: 10px;">{{ getLoginUserLanguage() == 'cn' ? 'Product' : 'Product' }}</th>
        <th style="color: #000; font-size: 10px;">Qty</th>
        <th style="color: #000; font-size: 10px;">Total</th>
    </tr>
    </thead>
    <tbody style="background-color: transparent;">
    @foreach($sale->saleItems  as $saleItem)
        <tr align="left">
            <td>{{$saleItem->product->name}}</td>
            <td>{{$saleItem->quantity}}</td>
            <td>{{ currencyAlignment(number_format((float)$saleItem->sub_total, 2))}}</td>
        </tr>
    @endforeach
    </tbody>
</table>
<hr>
<table width="100%" cellspacing="0" cellpadding="10" style="margin-top: 40px;">
    <tbody style="background-color: transparent;">
    <tr>
        <td align="left">{{ getLoginUserLanguage() == 'cn' ? 'Total' : 'Total' }}</td>
        <td align="right">{{currencyAlignment(number_format((float)$sale->grand_total, 2))}}</td>
    </tr>
    <tr>
        <td align="left">{{ getLoginUserLanguage() == 'cn' ? 'Paid' : 'Paid' }}</td>
        <td align="right">{{currencyAlignment(number_format((float)$sale->payments->sum('amount'), 2))}}</td>
    </tr>
    </tbody>
</table>
<p style="text-align: center; margin-top: 20px; font-size: 8px;">Thank you for your business!</p>
</body>
</html>
