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

/* =========================================================
   Auto-logout after 5 minutes of inactivity.
   Runs on every page that includes this file (i.e. every
   logged-in page, since they all include nav.php + script.js).
   Any real user interaction resets the timer; a barcode/RFID
   scan counts too, since a scanner's keystrokes fire keydown
   events just like typing does.
   ========================================================= */
(function() {
    "use strict";

    var IDLE_LIMIT_MS = 2 * 60 * 1000; // 2 minutes
    var idleTimer = null;

    function goToLogout() {
        window.location.href = "logout.php";
    }

    function resetIdleTimer() {
        if (idleTimer) clearTimeout(idleTimer);
        idleTimer = setTimeout(goToLogout, IDLE_LIMIT_MS);
    }

    ["mousemove", "mousedown", "keydown", "scroll", "touchstart", "click"].forEach(function(evt) {
        document.addEventListener(evt, resetIdleTimer, true);
    });

    resetIdleTimer();
})();