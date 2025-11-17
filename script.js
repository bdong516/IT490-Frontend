document.addEventListener('DOMContentLoaded', () => {
    fetch('data.json') // Fetch the JSON file
        .then(response => response.json()) // Parse the JSON response
        .then(data => {
            const jsonOutputElement = document.getElementById('jsonOutput');
            // Pretty-print the JSON and display it in the <pre> tag
            jsonOutputElement.textContent = JSON.stringify(data, null, 4);
        })
        .catch(error => console.error('Error fetching JSON:', error));
});
