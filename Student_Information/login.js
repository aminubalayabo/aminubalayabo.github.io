$(document).ready(function() {
    $('#loginForm').submit(function(e) {
        e.preventDefault();

        var formData = $(this).serialize();

        $.ajax({
            url: 'login.php',
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response === 'Login successful') {
                    window.location.href = 'profile.php?username=' + $('#username').val();
                } else {
                    alert(response);
                }
            },
            error: function() {
                alert('An error occurred during login.');
            }
        });
    });
});

