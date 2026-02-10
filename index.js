    function openGuide(page) {
        document.getElementById("guideFrame").src = page;
        document.getElementById("guideModal").style.display = "flex";
    }

    function closeGuide() {
        document.getElementById("guideModal").style.display = "none";
        document.getElementById("guideFrame").src = "";
    }

    function openReport() {
        document.getElementById("reportModal").style.display = "flex";
    }

    function closeReport() {
        document.getElementById("reportModal").style.display = "none";
    }

    window.addEventListener("click", function(e) {
        if (e.target.classList.contains("modal")) {
            e.target.style.display = "none";
        }
    });