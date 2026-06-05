(function() {
    const theme = localStorage.getItem("hms_theme") || "light";
    document.documentElement.setAttribute("data-theme", theme);
    const icon = document.getElementById("themeIcon");
    if (icon) icon.className = theme === "dark" ? "fas fa-sun" : "fas fa-moon";
})();
function toggleTheme() {
    const html = document.documentElement;
    const current = html.getAttribute("data-theme");
    const next = current === "dark" ? "light" : "dark";
    html.setAttribute("data-theme", next);
    localStorage.setItem("hms_theme", next);
    const icon = document.getElementById("themeIcon");
    if (icon) icon.className = next === "dark" ? "fas fa-sun" : "fas fa-moon";
}
