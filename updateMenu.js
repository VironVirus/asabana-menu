const fetch = require('node-fetch');

exports.handler = async (event, context) => {
    // Security: Only allow POST requests
    if (event.httpMethod !== "POST") {
        return { statusCode: 405, body: "Method Not Allowed" };
    }

    // Security: Verify user identity from Netlify Context
    const user = context.clientContext && context.clientContext.user;
    if (!user) {
        return { statusCode: 401, body: "Unauthorized: Please log in." };
    }

    // Server-side email restriction
    const ALLOWED_EMAILS = ['admin@asabanahotel.com', 'owner@example.com', 'your-actual-email@example.com'];
    if (!ALLOWED_EMAILS.includes(user.email)) {
        return { statusCode: 403, body: "Forbidden: Access restricted to authorized users." };
    }

    const { GITHUB_TOKEN, GITHUB_REPO, GITHUB_BRANCH = 'main' } = process.env;
    
    let body;
    try { body = JSON.parse(event.body); } catch(e) { return { statusCode: 400, body: "Invalid JSON" }; }
    
    const { filePath, content, message, isImage } = body;

    // 1. Check if file already exists to get its SHA (required for updates)
    let sha = "";
    try {
        const getFile = await fetch(
            `https://api.github.com/repos/${GITHUB_REPO}/contents/${encodeURIComponent(filePath)}?ref=${GITHUB_BRANCH}`,
            { headers: { Authorization: `token ${GITHUB_TOKEN}` } }
        );
        if (getFile.ok) {
            const fileData = await getFile.json();
            sha = fileData.sha;
        }
    } catch (err) {
        console.log("File might be new or unreachable:", filePath);
    }

    // 2. Prepare payload for GitHub
    // Content must be Base64 encoded for GitHub API
    const payload = {
        message: message || `Update ${filePath} via Admin Panel`,
        content: isImage 
            ? content 
            : Buffer.from(JSON.stringify(content, null, 2)).toString('base64'),
        branch: GITHUB_BRANCH
    };

    if (sha) payload.sha = sha;

    // 3. Commit to GitHub
    try {
        const response = await fetch(
            `https://api.github.com/repos/${GITHUB_REPO}/contents/${encodeURIComponent(filePath)}`,
            {
                method: 'PUT',
                headers: {
                    Authorization: `token ${GITHUB_TOKEN}`,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(payload)
            }
        );

        if (response.ok) {
            return {
                statusCode: 200,
                body: JSON.stringify({ message: "Success! Site will rebuild in a moment." })
            };
        } else {
            const error = await response.json();
            return { statusCode: 500, body: JSON.stringify(error) };
        }
    } catch (err) {
        return { statusCode: 500, body: err.toString() };
    }
};