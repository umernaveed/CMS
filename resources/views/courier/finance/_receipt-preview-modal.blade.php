@once
<div class="modal fade" id="paymentReceiptPreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Payment Receipt Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <iframe title="Payment receipt preview" data-receipt-frame style="display:block; width:100%; height:75vh; border:0;"></iframe>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalElement = document.getElementById('paymentReceiptPreviewModal');
    const frame = modalElement ? modalElement.querySelector('[data-receipt-frame]') : null;
    if (!modalElement || !frame || typeof bootstrap === 'undefined') return;

    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    document.querySelectorAll('[data-receipt-preview]').forEach(function (button) {
        button.addEventListener('click', function () {
            frame.src = button.dataset.receiptPreview || '';
            modal.show();
        });
    });
    modalElement.addEventListener('hidden.bs.modal', function () {
        frame.src = 'about:blank';
    });
});
</script>
@endonce
