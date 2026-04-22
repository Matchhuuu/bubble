<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bubble Hideout Login</title>

    <!-- ✅ Correct favicon path -->
    <link rel="icon" href="/media/BUBBLE.jpg">

    <!-- ✅ Correct font path -->
    <link rel="stylesheet" href="/fonts/fonts.css">

    <style>
        .box {
            position: relative;
            padding: 10px;
            border-radius: 15px;
            margin: auto;
            width: 25%;
        }

        label {
            color: black;
            position: relative;
            text-indent: 40%;
            font-weight: bold;
            font-size: 15px;
        }

        .input {
            border-color: transparent;
            border-radius: 25px;
            background-color: #337609;
            margin-bottom: 10px;
            color: #f0f0f0;
            height: 30px;
            width: 100%;
            text-indent: 10px;
            outline: none;
            box-shadow: 0px 5px 11px 1px rgba(0,0,0,0.28);
        }

        ::placeholder {
            color: #f0f0f0;
        }

        .btn {
            margin-top: 40px;
            background: #2c2c2c;
            width: 100px;
            height: 40px;
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: bold;
            box-shadow: 0px 5px 11px 1px rgba(0,0,0,0.28);
            cursor: pointer;
        }

        .btn:hover {
            background: #181818;
        }

        .img-bbl img {
            width: 300px;
            height: 300px;
        }

        .footer {
            color: #181818;
            text-align: center;
            font-size: 12px;
            width: 100%;
            height: 7%;
            position: absolute;
            bottom: 0;
            left: 0;
            z-index: 10;
        }

        .check {
            width: 17px;
            height: 17px;
            accent-color: #337609;
        }

        .toggle {
            display: flex;
            justify-content: right;
        }

        .aa {
            position: relative;
            display: flex;
            justify-content: center;
        }

        .bb {
            position: relative;
            height: 10px;
        }
    </style>
</head>

<body>

    <center>
        <div class="img-bbl">
            <!-- ✅ Fixed image path -->
            <img src="/media/BUBBLE.jpg" alt="Bubble Logo">
        </div>
    </center>

    <br>

    <div class="box">
        <center><h1>WELCOME TO BUBBLEAID</h1></center>
        <center><p>Smart Solutions for your Food Business</p></center>

        <!-- ✅ Correct form action -->
        <center>
            <form action="/interface/login.php" method="get">
                <button type="submit" class="btn">ENTER</button>
            </form>
        </center>
    </div>


    <script>
        function Toggle() {
            var x = document.getElementById("pass_input");
            if (x.type === "password") {
                x.type = "text";
            } else {
                x.type = "password";
            }
        }
    </script>

</body>
</html>
