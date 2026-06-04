// Confirm before placing an order
document.addEventListener("DOMContentLoaded", function() {
    const form = document.querySelector("form");
    if (form) {
        form.addEventListener("submit", function(e) {
            if (!confirm("Are you sure you want to simulate this purchase?")) {
                e.preventDefault();
            }
        });
    }
});