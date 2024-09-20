// GitHub repository details
const owner = 'aminubalayabo';
const repo = 'aminubalayabo.github.io';
const path = 'Student_Information/Students_Profile.txt';

document.getElementById('loginForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;

    try {
        const response = await axios.get(`https://api.github.com/repos/${owner}/${repo}/contents/${path}`);
        const content = atob(response.data.content);
        const lines = content.split('\n');

        for (let line of lines) {
            const fields = line.split(',');
            if (fields[0] === username && fields[1] === password) {
                // Login successful
                sessionStorage.setItem('currentUser', JSON.stringify(fields));
                // window.location.href = 'profile.html';
                window.location.href = 'studentDashboard.html';
                return;
            }
        }

        alert('Invalid username or password');
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred during login');
    }
});
