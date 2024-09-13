document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('student-registration-form');
    const addCourseButton = document.getElementById('add-course');
    const coursesContainer = document.getElementById('courses-container');
    let courseCount = 0;

    addCourseButton.addEventListener('click', () => {
        courseCount++;
        const courseDiv = document.createElement('div');
        courseDiv.innerHTML = `
            <h4>Course ${courseCount}</h4>
            <label for="course${courseCount}-code">Code:</label>
            <input type="text" id="course${courseCount}-code" name="course${courseCount}-code" required>
            
            <label for="course${courseCount}-title">Title:</label>
            <input type="text" id="course${courseCount}-title" name="course${courseCount}-title" required>
            
            <label for="course${courseCount}-units">Units:</label>
            <input type="number" id="course${courseCount}-units" name="course${courseCount}-units" required>
        `;
        coursesContainer.appendChild(courseDiv);
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(form);
        
        try {
            const response = await fetch('/api/register-student', {
                method: 'POST',
                body: formData
            });
            
            if (response.ok) {
                alert('Registration successful!');
                form.reset();
            } else {
                alert('Registration failed. Please try again.');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        }
    });
});
