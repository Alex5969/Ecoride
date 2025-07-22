document.addEventListener('DOMContentLoaded', function () {
    const switchButtons = document.querySelectorAll('.switch-form');
    const registerForm = document.querySelector('.register');
    const loginForm = document.querySelector('.login');

    switchButtons.forEach(button => {
        button.addEventListener('click', function (event) {
            event.preventDefault();
            const target = this.dataset.target;

            if (target === 'register') {
                loginForm.classList.remove('active');
                registerForm.classList.add('active');
            } else {
                registerForm.classList.remove('active');
                loginForm.classList.add('active');
            }

            // Cache les messages d'erreurs/succès précédents
            const errorContainers = document.querySelectorAll('.message.error-message-container, .message.success-message-container');
            errorContainers.forEach(container => container.style.display = 'none');
            document.getElementById('login-error').textContent = '';
            document.getElementById('register-error').textContent = '';
        });
    });

    const loginFormEl = document.getElementById('login-form');
    const registerFormEl = document.getElementById('register-form');

    const displayError = (formType, message) => {
        const errorDiv = document.getElementById(formType + '-error');
        if (errorDiv) {
            errorDiv.textContent = message;
        }
    };

    const validateEmail = (email) => {
        return /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-z]{2,}$/.test(email);
    };

    const validatePassword = (pwd) => {
        return /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/.test(pwd);
    };

    const validateUsername = (username) => {
        return /^[a-zA-Z0-9_-]{3,20}$/.test(username);
    };

    //formulaire de connexion
    if (loginFormEl) {
        loginFormEl.addEventListener('submit', (e) => {
            e.preventDefault();
            displayError('login', '');

            const email = loginFormEl.email.value.trim();
            const pwd = loginFormEl.password.value;

            if (!validateEmail(email)) {
                displayError('login', "Veuillez vérifier votre email.");
                loginFormEl.email.focus();
                return;
            }

            if (!validatePassword(pwd)) {
                displayError('login', "Mot de passe invalide.");
                loginFormEl.password.focus();
                return;
            }

            loginFormEl.querySelector('button[type="submit"]').disabled = true;
            loginFormEl.submit();
        });
    }

    //formulaire d'inscription
    if (registerFormEl) {
        registerFormEl.addEventListener('submit', (e) => {
            e.preventDefault();
            displayError('register', '');

            const username = registerFormEl.username.value.trim();
            const email = registerFormEl.email.value.trim();
            const pwd = registerFormEl.password.value;
            const confirmPwd = registerFormEl.confirm_password.value;
            const role = registerFormEl.role.value;

            if (!validateUsername(username)) {
                displayError('register', "Veuillez vérifier votre pseudo.");
                registerFormEl.username.focus();
                return;
            }

            if (!validateEmail(email)) {
                displayError('register', "Veuillez vérifier votre email.");
                registerFormEl.email.focus();
                return;
            }

            if (!validatePassword(pwd)) {
                displayError('register', "Mot de passe invalide.");
                registerFormEl.password.focus();
                return;
            }

            if (pwd !== confirmPwd) {
                displayError('register', "Les mots de passe ne correspondent pas.");
                registerFormEl.confirm_password.focus();
                return;
            }

            const allowedRoles = ['traveler', 'driver'];
            if (!role || !allowedRoles.includes(role)) {
                displayError('register', "Veuillez sélectionner un rôle valide.");
                registerFormEl.role.focus();
                return;
            }

            registerFormEl.querySelector('button[type="submit"]').disabled = true;
            registerFormEl.submit();
        });
    }

    // formualire avis post-trajet
    const validationModal = document.getElementById('validationModal');
    const closeButton = document.querySelector('.modal .close-button');
    const validateTripButtons = document.querySelectorAll('.validate-trip-button');
    const validationStatusRadios = document.querySelectorAll('input[name="validation_status"]');
    const ratingSection = document.getElementById('rating-section');
    const commentTextArea = document.getElementById('comment_issue');

    if (validationModal) {
        validateTripButtons.forEach(button => {
            button.addEventListener('click', function () {
                const tripId = this.dataset.tripId;
                const csrfToken = this.dataset.csrfToken;
                document.getElementById('modal-trip-id').value = tripId;
                document.getElementById('modal-csrf-token').value = csrfToken;
                validationModal.style.display = 'block';
                validationStatusRadios.forEach(radio => radio.checked = false);
                ratingSection.style.display = 'none';
                commentTextArea.value = '';
                commentTextArea.removeAttribute('required');
            });
        });

        closeButton.addEventListener('click', function () {
            validationModal.style.display = 'none';
        });

        window.addEventListener('click', function (event) {
            if (event.target === validationModal) {
                validationModal.style.display = 'none';
            }
        });

        validationStatusRadios.forEach(radio => {
            radio.addEventListener('change', function () {
                if (this.value === 'ok') {
                    ratingSection.style.display = 'block';
                    commentTextArea.removeAttribute('required');
                } else if (this.value === 'issue') {
                    ratingSection.style.display = 'none';
                    commentTextArea.setAttribute('required', 'required');
                }
            });
        });
    }

    const phpMessageContainers = document.querySelectorAll('.message.error-message-container, .message.success-message-container');
    phpMessageContainers.forEach(container => {
        if (container.textContent.trim() === '') {
            container.style.display = 'none';
        }
    });

    const createEmployeeForm = document.getElementById('create-employee-form');
    if (createEmployeeForm) {
        createEmployeeForm.addEventListener('submit', function (e) {
            const employeePassword = document.getElementById('employee_password').value;
            if (!validatePassword(employeePassword)) {
                e.preventDefault();
                displayError('create-employee-form-error', "Le mot de passe de l'employé est invalide. Minimum 8 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial.");
                document.getElementById('employee_password').focus();
            }
        });
    }

    // Affichage des graphiques
    if (typeof Chart !== 'undefined') {
        // Graphique des visites par jour
        if (document.getElementById('visitsPerDayChart')) {
            new Chart(document.getElementById('visitsPerDayChart'), {
                type: 'line',
                data: {
                    labels: chartVisitsLabels,
                    datasets: [{
                        label: 'Nombre de visites de la page d\'accueil',
                        data: chartVisitsData,
                        borderColor: 'rgb(75, 192, 192)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: { display: true, text: 'Nombre de visites' }
                        },
                        x: {
                            title: { display: true, text: 'Date' }
                        }
                    }
                }
            });
        }

        // Graphique des crédits gagnés
        if (document.getElementById('platformEarningsPerDayChart')) {
            new Chart(document.getElementById('platformEarningsPerDayChart'), {
                type: 'bar',
                data: {
                    labels: chartEarningsLabels,
                    datasets: [{
                        label: 'Crédits gagnés par la plateforme',
                        data: chartPlatformFeesData,
                        backgroundColor: 'rgba(75, 192, 192, 0.6)',
                        borderColor: 'rgb(75, 192, 192)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: { display: true, text: 'Crédits' }
                        },
                        x: {
                            title: { display: true, text: 'Date' }
                        }
                    }
                }
            });
        }
    }

    const burger = document.querySelector('.burger');
    const navLinks = document.querySelector('.nav-links');

    if (burger && navLinks) {
        burger.addEventListener('click', () => {
            navLinks.classList.toggle('active');
        });
    }
});
