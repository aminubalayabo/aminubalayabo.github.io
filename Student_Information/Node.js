const express = require('express');  
const fs = require('fs');  
const path = require('path');  
const bodyParser = require('body-parser');  

const app = express();  
const port = 3000;  

app.use(bodyParser.json());  

// Endpoint to handle the POST request  
app.post('/submit-info', (req, res) => {  
    const { username, password, email, appno } = req.body;  

    const data = `${username},${password},${email},${appno}\n`;  

    // Append the data to the file  
    fs.appendFile(path.join(__dirname, 'Details.txt'), data, err => {  
        if (err) {  
            return res.status(500).json({ error: 'Failed to write data' });  
        }  
        res.json({ message: 'Data saved successfully' });  
    });  
});  

app.listen(port, () => {  
    console.log(`Server running at http://localhost:${port}`);  
});
