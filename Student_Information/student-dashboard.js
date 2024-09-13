document.addEventListener('DOMContentLoaded', () => {
    const studentInfo = document.getElementById('student-info');
    const viewProfileBtn = document.getElementById('view-profile');
    const viewResultsBtn = document.getElementById('view-results');
    const modal = document.getElementById('modal');
    const modalBody = document.getElementById('modal-body');
    const closeBtn = document.querySelector('.close');

    const studentData = JSON.parse(sessionStorage.getItem('studentData'));

    if (!studentData) {
        window.location.href = 'index.html';
    }

    studentInfo.innerHTML = `<h2>Welcome, ${studentData.name}</h2>`;

    viewProfileBtn.addEventListener('click', async () => {
        try {
            const response = await fetch(`/api/student-profile?admissionNumber=${studentData.admissionNumber}`);
            if (response.ok) {
                const profileData = await response.json();
                modalBody.innerHTML = `
                    <h3>Student Profile</h3>
                    <img src="${profileData.passportUrl}" alt="Student Passport" width="100">
                    <p>Name: ${profileData.name}</p>
                    <p>Admission Number: ${profileData.admissionNumber}</p>
                    <p>Department: ${profileData.department}</p>
                    <p>Level: ${profileData.level}</p>
                    <p>Session: ${profileData.session}</p>
                    <p>Phone Number: ${profileData.phoneNumber}</p>
                    <p>Email: ${profileData.email}</p>
                    <h4>Courses:</h4>
                    <ul>
                        ${profileData.courses.map(course => `<li>${course.code} - ${course.title} (${course.units} units)</li>`).join('')}
                    </ul>
                `;
                modal.style.display = 'block';
            } else {
                alert('Failed to fetch profile data. Please try again.');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        }
    });

    viewResultsBtn.addEventListener('click', async () => {
        try {
            const response = await fetch(`/api/student-results?admissionNumber=${studentData.admissionNumber}`);
            if (response.ok) {
                const resultsData = await response.json();
                modalBody.innerHTML = `
                    <h3>Student Results</h3>
                    <p>Name: ${resultsData.name}</p>
                    <p>Admission Number: ${resultsData.admissionNumber}</p>
                    <p>Department: ${resultsData.department}</p>
                    <h4>Results:</h4>
                    <table>
                        <tr>
                            <th>Course Code</th>
                            <th>Course Title</th>
                            <th>Units</th>
                            <th>Grade</th>
                        </tr>
                        ${resultsData.results.map(result => `
                            <tr>
                                <td>${result.courseCode}</td>
                                <td>${result.courseTitle}</td>
                                <td>${result.units}</td>
                                <td>${result.grade}</td>
                            </tr>
                        `).join('')}
                    </table>
                    <p>GPA: ${resultsData.gpa}</p>
                    <p>CGPA: ${resultsData.cgpa}</p>
                `;
                modal.style.display = 'block';
            } else {
                alert('Failed to fetch results data. Please try again.');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        }
    });

    closeBtn.addEventListener('click', () => {
        modal.style.display = 'none';
    });

    window.addEventListener('click', (event) => {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
});
