<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; }
        h1 { font-size: 18px; margin-bottom: 0; }
        .muted { color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #e5e7eb; padding: 6px 8px; text-align: left; }
        th { background: #f3f4f6; }
        .totals td { border: none; }
        .totals { width: 40%; margin-left: auto; }
        .overdue { color: #b91c1c; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Invoice {{ $invoice->invoice_number }}</h1>
    <p class="muted">
        Status: {{ $invoice->status }}
        @if($invoice->isOverdue())
            &middot; <span class="overdue">OVERDUE</span>
        @endif
    </p>

    <p>
        @if($invoice->customer)
            <strong>Bill to:</strong> {{ $invoice->customer->name }}<br>
        @endif
        <strong>Issue date:</strong> {{ optional($invoice->issue_date)->format('Y-m-d') ?? 'N/A' }}<br>
        <strong>Due date:</strong> {{ optional($invoice->due_date)->format('Y-m-d') ?? 'N/A' }}
        @if($invoice->quotation)
            <br><strong>Quotation:</strong> {{ $invoice->quotation->quotation_number }}
        @endif
    </p>

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
            @foreach($invoice->items as $item)
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
        <tr><td>Subtotal</td><td>{{ $invoice->currency }} {{ number_format($invoice->subtotal, 2) }}</td></tr>
        <tr><td>Tax</td><td>{{ $invoice->currency }} {{ number_format($invoice->tax_amount, 2) }}</td></tr>
        <tr><td>Discount</td><td>-{{ $invoice->currency }} {{ number_format($invoice->discount_amount, 2) }}</td></tr>
        <tr><td><strong>Total</strong></td><td><strong>{{ $invoice->currency }} {{ number_format($invoice->total_amount, 2) }}</strong></td></tr>
        <tr><td>Paid</td><td>{{ $invoice->currency }} {{ number_format($invoice->paid_amount, 2) }}</td></tr>
        <tr><td><strong>Balance due</strong></td><td><strong>{{ $invoice->currency }} {{ number_format($invoice->balanceDue(), 2) }}</strong></td></tr>
    </table>

    @if($invoice->notes)
        <p><strong>Notes:</strong><br>{{ $invoice->notes }}</p>
    @endif
</body>
</html>
