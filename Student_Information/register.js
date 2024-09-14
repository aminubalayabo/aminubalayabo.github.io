$(document).ready(function() {
    $('#registrationForm').submit(function(e) {
        e.preventDefault();

        var formData = $(this).serialize();

        $.ajax({
            url: 'register.php',
            type: 'POST',
            data: formData,
            success: function(response) {
                alert(response);
                if (response === 'Registration successful') {
                    window.location.href = 'login.html';
                }
            },
            error: function() {
                alert('An error occurred during registration.');
            }
        });
    });
});
