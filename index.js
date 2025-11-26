/* ============================================
   NAVBAR DROPDOWN (CLICK)
============================================ */
const dropdown = document.querySelector(".dropdown");
const dropbtn = document.querySelector(".dropbtn");

if (dropdown && dropbtn) {
    dropbtn.addEventListener("click", (e) => {
        e.stopPropagation();
        dropdown.classList.toggle("active");
    });

    document.addEventListener("click", (e) => {
        if (!dropdown.contains(e.target)) {
            dropdown.classList.remove("active");
        }
    });
}



