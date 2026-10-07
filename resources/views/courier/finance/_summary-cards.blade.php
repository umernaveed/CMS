<div class="row g-3">
    <div class="col-md-3"><div class="panel p-4"><div class="text-muted">Invoice Income</div><div class="h3 mb-0">JMD {{ number_format($invoiceIncome, 2) }}</div></div></div>
    <div class="col-md-3"><div class="panel p-4"><div class="text-muted">Manual Income</div><div class="h3 mb-0">JMD {{ number_format($manualIncome, 2) }}</div></div></div>
    <div class="col-md-3"><div class="panel p-4"><div class="text-muted">Expenses</div><div class="h3 mb-0">JMD {{ number_format($expenses, 2) }}</div></div></div>
    <div class="col-md-3"><div class="panel p-4"><div class="text-muted">Profit / Loss</div><div class="h3 mb-0 {{ $profitLoss >= 0 ? 'text-success' : 'text-danger' }}">JMD {{ number_format($profitLoss, 2) }}</div></div></div>
</div>
