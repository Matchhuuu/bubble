<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="icon" href=/bubble/media/BUBBLE.jpg></link>

    <link rel="stylesheet" href="/bubble/fonts/fonts.css">
    <title>Bubble Hideout Login</title>
</head>
<style>
    
.box {
    position: relative;
    padding:10px; 
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
    background:#2c2c2c; 
    width: 100px; 
    height: 40px;
    color: white; 
    border: none;
    border-radius: 10px;
    font-weight: bold;
    box-shadow: 0px 5px 11px 1px rgba(0,0,0,0.28);
}

.btn:hover {
    background:#181818; 
}

.img-bbl {
    width: 30%;
    height: 30%;

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

.check{
    width: 17px;
    height: 17px;
    accent-color: #337609;
}

.toggle{
    display: flex;
    justify-content: right;
    
}

.aa{
    position: relative;
    display: flex;
    justify-content: center;
    
}

.bb{
    position: relative;
    height: 10px;
}
</style>

<body>
    <center> <div class="img-bbl">
        <img src="/bubble/media/BUBBLE.jpg" width="500px" height="500px"> 

    </div>
    <br></center>

    <div class="box">
        <center><h1>WELCOME TO BUBBLEAID</h1></center>
        <center><p>Your Need to Help Run your Business</p></center>

        <CENTer><form action="/bubble/interface/login.php"><button type="submit" class="btn"> ENTER </button></form></CENTer>
        
    </div>

    <div class="footer">

        <p>Bubble Hideout© 2024</p>

    </div>

<script>
    function Toggle() {
        var x = document.getElementById("pass_input");
        if (x.type === "password") {
            x.type = "text";
        } 
        else {
            x.type = "password";
        }
    }
</script>
    
</body>



   


</html>