document.addEventListener('DOMContentLoaded', function() {
  const switchButtons = document.querySelectorAll('.switch-form');
  const loginForm = document.querySelector('.login');
  const registerForm = document.querySelector('.register');

  switchButtons.forEach(button => {
    button.addEventListener('click', function(event) {
      event.preventDefault();
      const target = this.dataset.target;

      if (target === 'register') {
        loginForm.style.display = 'none';
        registerForm.style.display = 'block';
      } else {
        registerForm.style.display = 'none';
        loginForm.style.display = 'block';
      }
    });
  });
});

