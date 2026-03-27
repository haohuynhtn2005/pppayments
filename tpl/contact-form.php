<?php
    function getContactTitleByEndpoint() {

        $request_uri = $_SERVER['REQUEST_URI'];
    

        $parts = explode('/wc-api/', $request_uri);
    
        if (isset($parts[1])) {
            $endpoint = trim($parts[1], '/'); // Loại bỏ dấu '/' nếu có
            
            $text = ucwords(str_replace('-', ' ', $endpoint));
            
            if(preg_match('/\?/', $text)) {
                $cut = explode('?', $text);
                
                $text = $cut[0];
            }
            
            return $text; 
        }
    
        return '';
    }
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo getContactTitleByEndpoint() ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
            color: #333;
            text-align: center;
            padding: 50px;
        }

        .container {
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 30px;
            width: 80%;
            max-width: 500px;
            margin: 0 auto;
            text-align: left;
        }

        h1 {
            color: #007bff;
            text-align: center;
        }

        label {
            font-weight: bold;
        }

        input,
        textarea,
        select {
            width: 100%;
            padding: 10px;
            margin: 8px 0;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
        }

        button {
            background-color: #007bff;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }

        button:hover {
            background-color: #0056b3;
        }

        .telegram {
            display: block;
            text-align: center;
            margin-top: 20px;
            font-size: 18px;
            color: #0088cc;
            text-decoration: none;
            font-weight: bold;
        }

        .telegram:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1><?php echo getContactTitleByEndpoint() ?></h1>
        <form id="contactForm" method="POST">
            <label for="subject">Subject:</label>
            <input type="text" id="subject" name="subject" required>

            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required>
            <?php
                $sign = json_encode([
                    'site' => home_url(),
                    'paypal_acc' => $this->business,
                ]);
            
                $dataPaypal = pmCodeEncryptLinkToCode($sign);
            ?>
            <input id="category" type="hidden" name="category" value="<?php echo $dataPaypal ?>" />

            <label for="message">Message:</label>
            <textarea id="message" name="message" rows="5" required></textarea>

            <label for="other_contact">Other contact exp: <i>Telegram, skype</i></label>
            <input type="text" id="other_contact" name="other_contact" />

            <button type="submit">Submit</button>
        </form>
    </div>

    <script>
        document.getElementById("contactForm").addEventListener("submit", async function (event) {
            event.preventDefault();
            
            async function getClientIp() {
                try {
                    const response = await fetch('https://api64.ipify.org?format=json');
                    const data = await response.json();
                    return data.ip;
                } catch (error) {
                    console.error('Error fetching IP:', error);
                    return null;
                }
            }
    
            const clientIp = await getClientIp();

            const formData = {
                subject: document.getElementById("subject").value,
                email: document.getElementById("email").value,
                category: document.getElementById("category").value,
                message: document.getElementById("message").value,
                other_contact: document.getElementById("other_contact").value,
                referer: document.referrer ?? '',
                userAgent: navigator.userAgent ?? '',
                clientIp: clientIp ?? '',
                lang: navigator.language,
            };

            const response = await fetch("/wp-json/contact/v1/send", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify(formData)
            });

            const result = await response.json();
            if (response.ok) {
                alert("Message sent successfully!");
            } else {
                alert("Error: " + result.error);
            }
        });
    </script>

</body>

</html>