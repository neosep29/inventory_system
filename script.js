document.addEventListener("DOMContentLoaded", function() {
    const hamburgerButton = document.getElementById("sidebarToggleBtn");
    const sideNav = document.getElementById("sidebar");
    const sideNavOverlay = document.getElementById("sideNavOverlay");

    function openSidebar() {
        sideNav.classList.add("open");
        sideNavOverlay.classList.add("active");
        sideNavOverlay.style.display = "block";
    }

    function closeSidebar() {
        sideNav.classList.remove("open");
        sideNavOverlay.classList.remove("active");
        setTimeout(function() {
            if (!sideNav.classList.contains("open")) {
                sideNavOverlay.style.display = "none";
            }
        }, 300);
    }

    function toggleSidebar() {
        if (sideNav.classList.contains("open")) {
            closeSidebar();
        } else {
            openSidebar();
        }
    }

    hamburgerButton.addEventListener("click", function(e) {
        e.stopPropagation();
        toggleSidebar();
    });

    sideNavOverlay.addEventListener("click", closeSidebar);

    document.addEventListener("keydown", function(e) {
        if (e.key === "Escape" && sideNav.classList.contains("open")) {
            closeSidebar();
        }
    });

    const sidebarLinks = sideNav.querySelectorAll("a");
    sidebarLinks.forEach(function(link) {
        link.addEventListener("click", function() {
            closeSidebar();
        });
    });
});
