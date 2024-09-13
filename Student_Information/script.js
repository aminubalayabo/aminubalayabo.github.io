let studentsProfiles = []; // This would typically be loaded from Students_Profile.txt  
let loggedInUser = null;  

const sampleProfiles = `username,Admission number,name,Department,Level,Session,Phone Number,email,course information  
user1,2020171234,John Doe,BIOCHEMISTRY,100L,2022,1234567890,john@example.com,CSE101,Computer Science,3  
user2,2020123456,Jane Smith,BIOLOGY,200L,2021,0987654321,jane@example.com,BIO101,Biology,3`;  

// Function to load profiles into an array  
function loadProfiles() {  
    // In a real application, profiles would be read from a .txt file or backend.  
    studentsProfiles = sampleProfiles.split('\n').slice(1).map(line => {  
        const [username, admissionNumber, name, department, level, session, phoneNumber, email] = line.split(',');  
        return { username, admissionNumber, name, department, level, session, phoneNumber, email };  
    });  
}  

// Login Function  
function login() {  
    const username = document.getElementById("username").value;  
    const password = document.getElementById("password").value;  

    if (password === username) { // admission number as password  
        loggedInUser = studentsProfiles.find(profile => profile.username === username);  
        if (loggedInUser) {  
            alert("Login successful");  
            document.getElementById("loginForm").style.display = "none";  
            document.getElementById("studentDashboard").style.display = "block";  
        } else {  
            alert("Invalid username");  
        }  
    } else {  
        alert("Incorrect password");  
    }  
}  

// Function to view profile  
function viewProfile() {  
    if (loggedInUser) {  
        const profileHTML = `  
            <h3>Profile</h3>  
            <p>Name: ${loggedInUser.name}</p>  
            <p>Department: ${loggedInUser.department}</p>  
            <p>Level: ${loggedInUser.level}</p>  
            <p>Session: ${loggedInUser.session}</p>  
            <p>Email: ${loggedInUser.email}</p>  
            <img src="Passport/${loggedInUser.Admission_number}.jpg" alt="Profile Image">  
        `;  
        document.getElementById("resultsSummary").innerHTML = profileHTML;  
        document.getElementById("resultsSummary").style.display = 'block';  
    }  
}  

// Placeholder for results viewing  
function viewResults() {  
    alert("This functionality to view results is yet to be implemented.");  
}  

// Load profiles on page load  
loadProfiles();
