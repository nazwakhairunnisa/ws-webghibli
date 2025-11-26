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



/* ============================================
   SLIDER (Director & Character)
============================================ */
const slider = document.getElementById("directorSlider");
const slideRight = document.getElementById("slideRight");

if (slider && slideRight) {
    slideRight.addEventListener("click", () => {
        slider.scrollLeft += 250;
    });
}


/* ============================================
   RDF AUTO-LOAD NANTI BISA ADA DI SINI
============================================ */
// contoh placeholder saja
function loadRDFData() {
    console.log("RDF ready...");
}
