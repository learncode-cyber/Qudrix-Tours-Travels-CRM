<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $quotation->quotation_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; }
        h1 { font-size: 18px; margin-bottom: 0; }
        .muted { color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #e5e7eb; padding: 6px 8px; text-align: left; }
        th { background: #f3f4f6; }
        .totals td { border: none; }
        .totals { width: 40%; margin-left: auto; }
    </style>
</head>
<body>
    <h1>Quotation {{ $quotation->quotation_number }}</h1>
    <p class="muted">Version {{ $quotation->version }} &middot; Status: {{ $quotation->status }}</p>

    <p>
        <strong>Subject:</strong> {{ $quotation->subject }}<br>
        @if($quotation->customer)
            <strong>Customer:</strong> {{ $quotation->customer->name }}<br>
        @endif
        <strong>Valid until:</strong> {{ optional($quotation->valid_until)->format('Y-m-d') ?? 'N/A' }}
    </p>

    @if($quotation->description)
        <p>{{ $quotation->description }}</p>
    @endif

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th>Qty</th>
                <th>Unit Price</th>
                <th>Discount</th>
                <th>Tax %</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($quotation->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ number_format($item->unit_price, 2) }}</td>
                    <td>{{ number_format($item->discount, 2) }}</td>
                    <td>{{ number_format($item->tax_rate, 2) }}</td>
                    <td>{{ number_format($item->total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td>{{ $quotation->currency }} {{ number_format($quotation->subtotal, 2) }}</td></tr>
        <tr><td>Tax</td><td>{{ $quotation->currency }} {{ number_format($quotation->tax_amount, 2) }}</td></tr>
        <tr><td>Discount</td><td>-{{ $quotation->currency }} {{ number_format($quotation->discount_amount, 2) }}</td></tr>
        <tr><td><strong>Total</strong></td><td><strong>{{ $quotation->currency }} {{ number_format($quotation->total_amount, 2) }}</strong></td></tr>
    </table>

    @if($quotation->notes)
        <p><strong>Notes:</strong><br>{{ $quotation->notes }}</p>
    @endif
</body>
</html>
