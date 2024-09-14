// GitHub repository details
const owner = 'aminubalayabo';
const repo = 'aminubalayabo.github.io';
const path = 'Student_Information/Students_Results.txt';

// Generate course fields
function generateCourseFields() {
    const courseFields = document.getElementById('courseFields');
    for (let i = 1; i <= 14; i++) {
        courseFields.innerHTML += `
            <h3>Course ${i}</h3>
            <label for="course${i}code">Course ${i} Code:</label>
            <input type="text" id="course${i}code" name="course${i}code"><br><br>
            
            <label for="course${i}title">Course ${i} Title:</label>
            <input type="text" id="course${i}title" name="course${i}title"><br><br>
            
            <label for="course${i}units">Course ${i} Units:</label>
            <input type="number" id="course${i}units" name="course${i}units"><br><br>
        `;
    }
}

// Handle form submission
document.getElementById('registrationForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const data = Object.fromEntries(formData.entries());

    // Prepare the data string
    let dataString = `${data.username},${data.admission_number},${data.name},${data.department},${data.level},${data.session},${data.phone_number},${data.email}`;
    for (let i = 1; i <= 14; i++) {
        dataString += `,${data[`course${i}code`]},${data[`course${i}title`]},${data[`course${i}units`]}`;
    }
    dataString += '\n';

    try {
        // Get the current file content
        const response = await axios.get(`https://api.github.com/repos/${owner}/${repo}/contents/${path}`);
        const currentContent = atob(response.data.content);

        // Check if user already exists
        if (currentContent.includes(data.username) || currentContent.includes(data.admission_number)) {
            alert('User already exists');
            return;
        }

        // Append new data
        const updatedContent = currentContent + dataString;

        // Update the file in the repository
        await axios.put(`https://api.github.com/repos/${owner}/${repo}/contents/${path}`, {
            message: 'Add new student registration',
            content: btoa(updatedContent),
            sha: response.data.sha
        }, {
            headers: {
                'Authorization': 'Bearer YOUR_GITHUB_PERSONAL_ACCESS_TOKEN'
            }
        });

        alert('Registration successful');
        this.reset();
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred during registration');
    }
});

// Initialize course fields on page load
generateCourseFields();
