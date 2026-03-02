<!DOCTYPE html>
<html lang="en" class="light-theme" style="color-scheme: light;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Authentication Failed — Laravel Cloud</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,100..900&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", system-ui, sans-serif;
            background: #FFFFFF;
            color: #1d1f21;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .card {
            background: #ffffff;
            border-radius: 6px;
            box-shadow: rgba(0, 0, 0, 0.06) 0px 1px 2px 0px, rgba(0, 0, 0, 0.03) 0px 0px 1px 0px;
            width: 100%;
            max-width: 440px;
            padding: 2rem;
            text-align: center;
        }
        .icon {
            width: 3rem;
            height: 3rem;
            margin: 0 auto 20px;
            background: #eff0f1;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .icon svg {
            width: 1.5rem;
            height: 1.5rem;
            color: #151718;
        }
        h1 {
            margin: 0 0 8px;
            font-size: 1.25rem;
            font-weight: 600;
            color: #1d1f21;
            letter-spacing: -0.02em;
        }
        p {
            margin: 0;
            font-size: 0.875rem;
            line-height: 1.5;
            color: #626465;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
            </svg>
        </div>
        <h1>Authentication Failed</h1>
        <p>Invalid request or authentication was cancelled. Please try again from the terminal.</p>
    </div>
</body>
</html>
