<?php
// test-contact-debug.php
// Debug page for contact form

session_start();

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Contact Form Debug</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { margin-top: 0; color: #333; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        .result { margin-top: 20px; padding: 15px; border-radius: 4px; display: none; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background: #e7f3ff; color: #004085; border: 1px solid #b8daff; padding: 10px; margin-bottom: 20px; }
        .token { word-break: break-all; background: #f8f9fa; padding: 10px; border: 1px solid #dee2e6; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Contact Form Debug Tool</h1>
        
        <div class="info">
            <strong>Session Status:</strong> <?php echo session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive'; ?><br>
            <strong>Session ID:</strong> <?php echo session_id(); ?><br>
            <strong>CSRF Token:</strong> 
            <div class="token"><?php echo $_SESSION['csrf_token']; ?></div>
        </div>
        
        <form id="debugForm">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <!-- Honeypot fields -->
            <div style="display: none;">
                <input type="text" name="website">
                <input type="text" name="url">
                <input type="text" name="phone">
            </div>
            
            <div class="form-group">
                <label>Name:</label>
                <input type="text" name="name" value="Test User" required>
            </div>
            
            <div class="form-group">
                <label>Email:</label>
                <input type="email" name="email" value="test@example.com" required>
            </div>
            
            <div class="form-group">
                <label>Subject:</label>
                <input type="text" name="subject" value="Test Subject">
            </div>
            
            <div class="form-group">
                <label>Message:</label>
                <textarea name="message" required>This is a test message to debug the contact form.</textarea>
            </div>
            
            <button type="submit">Send Test Message</button>
        </form>
        
        <div id="result" class="result"></div>
    </div>
    
    <script>
    document.getElementById('debugForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const resultDiv = document.getElementById('result');
        
        resultDiv.style.display = 'none';
        resultDiv.className = 'result';
        resultDiv.innerHTML = 'Sending...';
        resultDiv.style.display = 'block';
        
        try {
            const response = await fetch('contact-handler.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            resultDiv.className = 'result ' + (data.success ? 'success' : 'error');
            resultDiv.innerHTML = '<strong>Response:</strong><br>' + JSON.stringify(data, null, 2);
            resultDiv.style.display = 'block';
            
            // Update token if provided
            if (data.new_token) {
                document.querySelector('input[name="csrf_token"]').value = data.new_token;
            }
            
        } catch (error) {
            resultDiv.className = 'result error';
            resultDiv.innerHTML = '<strong>Error:</strong><br>' + error.message;
            resultDiv.style.display = 'block';
        }
    });
    </script>
</body>
</html>