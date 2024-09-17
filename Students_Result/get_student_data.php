<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Login</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 0px; text-align: center; position: center; }
        h2, h3 { font-family: "Trebuchet MS", Arial, Helvetica, sans-serif; text-align: center; }
        #loginForm, #results { margin-top: 30px; text-align: center; }
        input, button { margin: 10px; padding: 5px; font-family: "Trebuchet MS", Arial, Helvetica, sans-serif; text-align: center; }
        table { width: 40%; border-collapse: collapse; style="margin: 0 auto;" }
        th, td { border: 1px solid #ddd; padding: 4px; text-align: center; }
        th { background-color: #f2f2f2; }
/*         img { margin-left: 20px; } */
    </style>
</head>
<body>
    <img src="udus.logo.jpg" />
    <h2 >SCHOOL OF BASIC AND ADVANCED STUDIES </h2>
    <h3>2023/2024 Academic Session Results</h3>
    <div id="loginForm">
        <cnter>
        <input type="text" id="username" placeholder="Username" required><br>
        <input type="password" id="password" placeholder="Password" required><br>
        <button onclick="login()">Login</button>

    </div>
    <div id="results" style="display:none;">
        <div id="averageScore" style="display: none;"></div>
        <p id="studentName"> <span><h3></h3>Your Results are as Follwos:</h3></span> </p>
        <center>
        <table id="scoresTable" width: 100%; border-collapse: collapse;>
            <tr>
                <th>Subject</th>
                <th>Score</th>
            </tr>
        </table>
        </center>
        <p id="totalScore"></p>
        <p id="averageScore"></p>
        <p id="aggregateScore"></p>
        <p id="remarks"></p>
     <a href="#" id="logoutLink">Logout</a>
    </div>
</cnter>
    <marquee><h3><span class="orange" style="color:rgb(221, 135, 222)";> For enquiries, please send a mail to aminubala@gmail.com! Thank you </span></h3></marquee>
    <script>
        async function login() {
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            
            try {
                const response = await fetch('https://raw.githubusercontent.com/aminubalayabo/aminubalayabo.github.io/main/SOBAS/students_results.txt');
                const data = await response.text();
                const lines = data.split('\n');
                
                for (let line of lines) {
                    const [user, pass, ...scores] = line.split(',');
                    if (user === username && pass === password) {
                        displayResults(user, scores);
                        return;
                    }
                }
                
                alert('Invalid username or password');
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while fetching data');
            }
        }

        function displayResults(name, scores) {
            const subjects = ['BIO', 'CHEM', 'ENG', 'MATHS', 'PHY'];
            const numericScores = scores.map(Number);
            const total = numericScores.reduce((a, b) => a + b, 0);
            const average = total / numericScores.length;
            const aggregate = Math.round(total / 500 * 400);
            const remarks = aggregate >= 160 ? 'Pass Matric' : 'Fail Matric!';

            document.getElementById('loginForm').style.display = 'none';
            document.getElementById('results').style.display = 'block';
            document.getElementById('studentName').textContent = `UserName: ${name}`;

            const table = document.getElementById('scoresTable');
    table.innerHTML = '<tr><th>Subject</th><th>Score</th></tr>'; // Clear previous results
    
            
            for (let i = 0; i < subjects.length; i++) {
                const row = table.insertRow(-1);
                const subjectCell = row.insertCell(0);
                const scoreCell = row.insertCell(1);
                subjectCell.textContent = subjects[i];
                scoreCell.textContent = numericScores[i];
            }

            document.getElementById('totalScore').textContent = `Total Score (500): ${total}`;
            document.getElementById('averageScore').textContent = `Average Score (400): ${average.toFixed(2)}`;
            document.getElementById('aggregateScore').textContent = `Aggregate Score (400): ${aggregate.toFixed(2)}`;
            document.getElementById('remarks').textContent = `Remarks: ${remarks}`;
        }
 document.getElementById('logoutLink').addEventListener('click', function(e) {
    e.preventDefault();
    document.getElementById('loginForm').style.display = 'block';
    document.getElementById('results').style.display = 'none';
    document.getElementById('username').value = '';
    document.getElementById('password').value = '';
});
        
    </script>
</body>
</html>
