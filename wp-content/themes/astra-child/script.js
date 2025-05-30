// Scroll-to-top button logic

document.addEventListener('DOMContentLoaded', function() {
  // Create the button
  const btn = document.createElement('button');
  btn.id = 'scrollToTopBtn';
  btn.innerHTML = '↑';
  btn.style.display = 'none';
  btn.style.position = 'fixed';
  btn.style.bottom = '40px';
  btn.style.right = '40px';
  btn.style.zIndex = '999';
  btn.style.background = '#0073e6';
  btn.style.color = '#fff';
  btn.style.border = 'none';
  btn.style.borderRadius = '50%';
  btn.style.width = '48px';
  btn.style.height = '48px';
  btn.style.fontSize = '24px';
  btn.style.cursor = 'pointer';
  btn.style.boxShadow = '0 2px 8px rgba(0,0,0,0.2)';
  btn.style.transition = 'background 0.2s';
  document.body.appendChild(btn);

  // Show/hide button on scroll
  window.addEventListener('scroll', function() {
    btn.style.display = (window.scrollY > 400) ? 'block' : 'none';
  });

  // Scroll to top on click
  btn.addEventListener('click', function() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });
}); 