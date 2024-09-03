let studentData = [];

fetch('get_student_data.php')
    .then(response => response.json())
    .then(data => {
        studentData = data;
        document.getElementById('loadingMessage').style.display = 'none';
        document.getElementById('loginForm').style.display = 'block';
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('loadingMessage').textContent = 'Error loading student data. Please try again later.';
    });

document.getElementById('loginForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;
    

    
            

    const student = studentData.find(s => s.username === username && s.password === password);

    if (student) {
        displayResults(student.scores);
    } else {
        alert('Invalid username or password');
    }
});


  
            const loginForm = document.getElementById('loginForm');  
            const welcomeMessage = document.getElementById('welcomeMessage');  

            loginForm.addEventListener('submit', function(event) {  
                event.preventDefault();  
                const username = document.getElementById('username').value;  
                localStorage.setItem('username', username);  
                displayWelcomeMessage();  
            });  


            
            function displayWelcomeMessage() {  
                const username = localStorage.getItem('username');  
                if (username) {  
                    welcomeMessage.textContent = `Welcome, ${username}!`;  
                    welcomeMessage.classList.remove('hidden');  
                }  
            }  

            // Display welcome message if user is already logged in  
            displayWelcomeMessage();  
        
            
            

          
            function updateTime() {  
                const now = new Date();  
                const options = { hour: '2-digit', minute: '2-digit', second: '2-digit' };  
                document.getElementById('time').textContent = now.toLocaleTimeString(undefined, options);  
            }  
            setInterval(updateTime, 1000);  
            window.onload = updateTime;  
          



function displayResults(scores) {
    document.getElementById('loginForm').style.display = 'none';
    document.getElementById('resultDisplay').style.display = 'block';

    const table = document.getElementById('resultsTable');
    table.innerHTML = '<tr><th>Subject</th><th>Score</th></tr>'; // Clear previous results
    let totalScore = 0;

    for (const [subject, score] of Object.entries(scores)) {
        const row = table.insertRow();
        row.insertCell(0).textContent = subject;
        row.insertCell(1).textContent = score;
        totalScore += score;
    }

    const averageScore = totalScore / Object.keys(scores).length;
    const aggregateScore = Math.round(totalScore / 500*400);
    if (aggregateScore > 159) {
        remarks = "Pass Matric";
        } else {
        remarks = "Fail Matric!"
        } 
    document.getElementById('totalScore').textContent = totalScore;
    document.getElementById('averageScore').textContent = averageScore.toFixed(2);
    document.getElementById('aggregateScore').textContent = aggregateScore.toFixed(3);
    document.getElementById('remarks').textContent = remarks;
}

    document.getElementById('logoutLink').addEventListener('click', function(e) {
    e.preventDefault();
    document.getElementById('loginForm').style.display = 'block';
    document.getElementById('resultDisplay').style.display = 'none';
    document.getElementById('username').value = '';
    document.getElementById('password').value = '';
});