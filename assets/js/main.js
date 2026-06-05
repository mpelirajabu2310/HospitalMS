function toggleSidebar() {
    document.getElementById("sidebar").classList.toggle("show");
    let overlay = document.querySelector(".sidebar-overlay");
    if (!overlay) {
        overlay = document.createElement("div");
        overlay.className = "sidebar-overlay";
        overlay.onclick = function() { document.getElementById("sidebar").classList.remove("show"); this.classList.remove("show"); };
        document.body.appendChild(overlay);
    }
    overlay.classList.toggle("show");
}
function toggleSidebarCollapse() {
    document.getElementById("sidebar").classList.toggle("collapsed");
}
document.addEventListener("DOMContentLoaded", function() {
    const notifBtn = document.getElementById("notificationBtn");
    if (notifBtn) {
        notifBtn.addEventListener("click", function(e) { e.stopPropagation(); this.classList.toggle("show"); if (this.classList.contains("show")) loadNotifications(); });
        document.addEventListener("click", function() { notifBtn.classList.remove("show"); });
    }
    const searchInput = document.getElementById("globalSearch");
    const searchResults = document.getElementById("searchResults");
    if (searchInput) {
        let timeout;
        searchInput.addEventListener("input", function() {
            clearTimeout(timeout);
            const q = this.value.trim();
            if (q.length < 2) { searchResults.classList.add("d-none"); return; }
            timeout = setTimeout(function() {
                fetch(APP_URL + "/api/search.php?q=" + encodeURIComponent(q))
                    .then(r => r.json()).then(data => {
                        if (data.length === 0) { searchResults.innerHTML = '<div class="p-3 text-muted text-center">No results found</div>'; }
                        else { searchResults.innerHTML = data.map(d => '<a href="' + d.url + '" class="d-block px-3 py-2 border-bottom text-decoration-none"><div class="d-flex align-items-center gap-2"><i class="fas ' + d.icon + ' text-primary"></i><div><strong>' + d.label + '</strong><br><small class="text-muted">' + d.sub + '</small></div></div></a>').join(""); }
                        searchResults.classList.remove("d-none");
                    });
            }, 300);
        });
        document.addEventListener("click", function(e) { if (!searchInput.contains(e.target)) searchResults.classList.add("d-none"); });
    }
    document.querySelectorAll(".alert-dismissible").forEach(function(el) { setTimeout(function() { el.remove(); }, 5000); });
    document.querySelectorAll(".select2").forEach(function(el) { if (typeof $ !== "undefined") { $(el).select2({ theme: "bootstrap-5", width: "100%" }); } });
});
function loadNotifications() {
    const body = document.getElementById("notifBody");
    if (!body) return;
    fetch(APP_URL + "/api/notifications.php").then(r => r.json()).then(data => {
        if (data.length === 0) { body.innerHTML = '<div class="text-center text-muted py-3">No notifications</div>'; }
        else { body.innerHTML = data.map(function(n) { return '<div class="notif-item' + (n.is_read == 0 ? " unread" : "") + '" onclick="window.location.href=\'' + (n.link || "#") + '\'"><strong>' + n.title + '</strong><br><small>' + n.message + '</small><br><small class="text-muted">' + n.time_ago + '</small></div>'; }).join(""); }
        document.getElementById("notifCount").textContent = data.filter(function(n) { return n.is_read == 0; }).length;
    });
}
function markAllRead() { fetch(APP_URL + "/api/notifications.php?action=mark_read", { method: "POST" }).then(function() { loadNotifications(); }); }
