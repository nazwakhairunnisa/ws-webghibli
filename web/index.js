/* ============================================
   NAVBAR MOBILE TOGGLE
============================================ */
const menuToggle = document.querySelector(".menu-toggle");
const navLinks = document.querySelector(".nav-links");

if (menuToggle && navLinks) {
    menuToggle.addEventListener("click", () => {
        navLinks.classList.toggle("active");
    });

    // Close menu when clicking nav link
    const navItems = document.querySelectorAll(".nav-links a");
    navItems.forEach(item => {
        item.addEventListener("click", () => {
            navLinks.classList.remove("active");
        });
    });
}

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

// Smooth scroll for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
  anchor.addEventListener('click', function (e) {
    e.preventDefault();
    const target = document.querySelector(this.getAttribute('href'));
    if (target) {
      target.scrollIntoView({
        behavior: 'smooth',
        block: 'start'
      });
    }
  });
});

/* ============================================
   SLIDER CONTROLS (LEFT/RIGHT BUTTONS)
============================================ */
const slideButtons = document.querySelectorAll(".slide-btn");

slideButtons.forEach(button => {
    button.addEventListener("click", () => {
        const sliderId = button.getAttribute("data-slider");
        const slider = document.getElementById(sliderId);
        
        if (!slider) return;

        const scrollAmount = 220; // Adjust based on card width + gap
        
        if (button.classList.contains("slide-left")) {
            slider.scrollLeft -= scrollAmount;
        } else {
            slider.scrollLeft += scrollAmount;
        }
    });
});

/* ============================================
   SMOOTH SCROLL FOR ANCHOR LINKS
============================================ */
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        const targetId = this.getAttribute('href');
        
        // Skip if it's just "#"
        if (targetId === "#") return;
        
        const targetElement = document.querySelector(targetId);
        
        if (targetElement) {
            e.preventDefault();
            
            const navbarHeight = document.querySelector('.navbar').offsetHeight;
            const targetPosition = targetElement.offsetTop - navbarHeight - 20;
            
            window.scrollTo({
                top: targetPosition,
                behavior: 'smooth'
            });
        }
    });
});

/* ============================================
   ACTIVE NAV LINK ON SCROLL
============================================ */
window.addEventListener('scroll', () => {
    const sections = document.querySelectorAll('.section-block');
    const navLinks = document.querySelectorAll('.nav-links a');
    
    let current = '';
    
    sections.forEach(section => {
        const sectionTop = section.offsetTop;
        const sectionHeight = section.clientHeight;
        
        if (window.pageYOffset >= (sectionTop - 200)) {
            current = section.getAttribute('id');
        }
    });
    
    navLinks.forEach(link => {
        link.classList.remove('active');
        if (link.getAttribute('href') === `#${current}`) {
            link.classList.add('active');
        }
    });
});

/* ============================================
   RDF DATA LOADING (PLACEHOLDER)
============================================ */
function loadRDFData() {
    console.log("Ready to load RDF data...");
    // Nanti bisa ditambahin fetch RDF di sini
}

// Optional: Call on page load
// loadRDFData();