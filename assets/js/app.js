const APP_URL = "http://localhost/HospitalMS";
document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll(".datatable").forEach(function(table) {
        if (typeof $ !== "undefined" && $.fn.DataTable) {
            $(table).DataTable({
                pageLength: 25,
                language: { search: "", searchPlaceholder: "Search...", lengthMenu: "_MENU_ per page", info: "Showing _START_ to _END_ of _TOTAL_ entries", infoEmpty: "No entries" },
                dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>><'row'<'col-sm-12'tr>><'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
                columnDefs: [{ targets: "no-sort", orderable: false }]
            });
        }
    });
    initRevenueChart();
    initVisitChart();
});
function initRevenueChart() {
    const canvas = document.getElementById("revenueChart");
    if (!canvas || typeof months === "undefined") return;
    const sym = typeof currencySymbol !== "undefined" ? currencySymbol : "TZS";
    new Chart(canvas, {
        type: "line",
        data: { labels: months, datasets: [{ label: "Revenue (" + sym + ")", data: revenues, borderColor: "#0d6efd", backgroundColor: "rgba(13,110,253,.1)", fill: true, tension: .4, pointRadius: 4, pointBackgroundColor: "#0d6efd" }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { callback: function(v) { return sym + " " + v.toLocaleString(); } } } } }
    });
}
function initVisitChart() {
    const canvas = document.getElementById("visitChart");
    if (!canvas || typeof visitTypes === "undefined") return;
    const colors = { outpatient: "#0d6efd", inpatient: "#198754", emergency: "#dc3545", followup: "#ffc107" };
    new Chart(canvas, {
        type: "doughnut",
        data: { labels: visitTypes.map(function(v) { return v.type; }), datasets: [{ data: visitTypes.map(function(v) { return parseInt(v.count); }), backgroundColor: visitTypes.map(function(v) { return colors[v.type] || "#6c757d"; }) }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "bottom", labels: { font: { size: 11 }, padding: 12 } } }, cutout: "65%" }
    });
}
