<?php
session_start();
?>
<!DOCTYPE html>
<html>
<head>
     <link rel="stylesheet" href="/bubble/fonts/fonts.css">
<title>Order Expired</title>
<style>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}
.box {
    position: relative;
    padding:10px; 
    border-radius: 15px;
    margin: auto;
    width: 100%;
}

.footer {
    color: #181818;
    text-align: center;
    font-size: 12px;
    
    width: 100%;
    background-color: white;
    position: relative; 
    bottom: 0; 
    left: 0; 
    z-index: 10;
}

.sec3 {
   
    display: flex;
    flex-direction: column;
    align-items: center;   /* horizontal center */
    justify-content: center; /* vertical center */
    text-align: center; 
    z-index: 5;

    
}

.info-parent {
    position: relative;

    box-sizing: border-box;
    display: flex;
    flex-direction: row;
    flex-wrap: wrap;
    justify-content: center;
    padding-top: 20px;
    width: 100%;
    gap: 5px; 
}

.stars {
  display: flex;
  flex-direction: row-reverse;
  justify-content: center;
  gap: 5px;
}

.stars input {
  display: none;
}

.stars label {
  font-size: 2.5rem;
  color: #ccc;
  cursor: pointer;
  transition: color 0.2s;
}

/* Highlight stars on selection */
.stars input:checked ~ label {
  color: #ffc700;
}

/* Highlight on hover */
.stars label:hover,
.stars label:hover ~ label {
  color: #ffdd66;
}

.info-contact1 {
  background-color: white;
  align-content: center;
  border: #7e5832 solid 2px;
  color: #1a1a1a;
  text-align: justify;
  padding: 20px;
  border-radius: 8px;
  margin: 10px;
  width: 20%;
  height: auto;
  max-width: 500px;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
  word-wrap: break-word; /* ensures long words break */
}

.info-contact2 {
  background-color: white;
  border: #7e5832 solid 2px;
  color: #1a1a1a;
  text-align: justify;
  padding: 20px;
  border-radius: 8px;
  margin: 10px;
  width: 70%;
  height: auto;
  
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
  word-wrap: break-word; /* ensures long words break */
}

#name, #comment{
    width: 95%;
}

#comm_btn{
    width: 30%;
}

.rate-btn{
  font-family: Poppins;
  font-size: 15px;
  background-color: transparent;
  padding: 10px;
  border: #1a1a1a solid 2px;
  width: 70%;
  border-radius: 24px;

  transition: ease 0.5s;
}

.rate-btn:hover{

  font-weight: bolder;
  border-radius: 30px;

  transition: ease 0.5s;
}

.feedback-form {
  width: 100%;
  margin: 0 auto;
  padding: 20px;
  border-radius: 10px;
  
  font-family: Poppins;
}

.feedback-form h3 {
  text-align: center;
  margin-bottom: 20px;
}

.feedback-form label {
  display: block;
  margin-top: 10px;
  margin-bottom: 5px;
  font-weight: bold;
}

.feedback-form input,
.feedback-form textarea {
  width: 100%;
  padding: 10px;
  border: 1px solid #1a1a1a;
  border-radius: 5px;
  font-family: inherit;
  font-size: 1rem;
  box-sizing: border-box;
}

.feedback-form input:focus,
.feedback-form textarea:focus {
  outline: none;
  
  box-shadow: none;      /* optional: remove glow */
}

.feedback-form button {
  margin-top: 15px;
  font-family: Poppins;
  width: 100%;
  padding: 12px;
  color: #1a1a1a;
  border-radius: 6px;
  font-size: 1rem;
  cursor: pointer;
  background-color: transparent;
  border: #1a1a1a solid 2px;

  transition: background-color 0.2s ease;
}

.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  display: none;
  align-items: center;
  justify-content: center;
  z-index: 999;
}

.modal-content {
  background: white;
  padding: 30px;
  border-radius: 10px;
  text-align: center;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
  font-size: 1.2rem;
}

.modal-content button {
  margin-top: 15px;
  padding: 8px 20px;
  font-family: Poppins;
  background-color: #337609;
  color: white;
  border: none;
  border-radius: 6px;
  cursor: pointer;
}

#center{
  align-content: center;
}
@media (max-width: 768px) {

    .box{
        margin: auto;
        width: 90%;
    }

    .info-contact1, .info-contact2 {
        width: 90%;
        max-width: 90%;
    }

    .info-contact2 input, textarea{
        width: 95%;
    }

}

</style>
</head>
<body>
    <center> <div class="img-bbl">
        <img src="/bubble/media/BUBBLE.jpg" width="200px" height="200px"> 

    </div>
    <br></center>

    <div class="box">
        <center><h1>Thank You for Order</h1></center>
        <center><p>Please wait 15 - 20 for you order!</p></center>

        <section class="sec3" id="contacts">
            
        <div class="info-parent">

            <div class="info-contact1">
                <form class="star-rating-form" id="starRating" method="POST">
                <center><h3 style="font-size: 30px; margin-top: -10px; margin-bottom: -5px;">Rate Us</h3></center>

                <div class="stars">
                    <input type="radio" name="rating" id="star5" value="5">
                    <label for="star5" title="5 stars">★</label>

                    <input type="radio" name="rating" id="star4" value="4">
                    <label for="star4" title="4 stars">★</label>

                    <input type="radio" name="rating" id="star3" value="3">
                    <label for="star3" title="3 stars">★</label>

                    <input type="radio" name="rating" id="star2" value="2">
                    <label for="star2" title="2 stars">★</label>

                    <input type="radio" name="rating" id="star1" value="1">
                    <label for="star1" title="1 star">★</label>
                </div>

                <br><br>

                <center><button class="rate-btn" type="submit">Submit</button></center>
                </form>
            </div>
            
            <div class="info-contact2">
                <h2 style="font-size: 30px; margin-top: -10px; margin-bottom: -10px;">LET US HEAR YOUR FEEDBACK!</h2>
                
                <form class="feedback-form" id="feedbackForm" method="POST">
                
                <label for="name">Your Name: <i style="color: gray; opacity: 0.5;">Optional</i></label>
                <input type="text" id="name" name="name" placeholder="Enter your name (optional)" autocomplete="off">

                <label for="comment">Your Comment:</label>
                <textarea id="comment" name="comment" rows="5" placeholder="Write your feedback here..." required></textarea>

                <center><button id="comm_btn" type="submit">Submit</button></center>
                </form>
              
            </div>
        </div>
        </section>    

        <!-- Thank You Modal for Comment -->
<div id="thankYouModal" class="modal-overlay">
  <div class="modal-content">
    <p>Thank you for your response!</p>
    <button onclick="closeModal()">Close</button>
  </div>
</div>

<!-- Thank You Modal for Star Rating -->
<div id="starThankYouModal" class="modal-overlay">
  <div class="modal-content">
    <p>Thank you for rating us!</p>
    <button onclick="closeStarModal()">Close</button>
  </div>
</div>
    </div>


    <div class="footer">

        <p>Bubble Hideout© 2024</p>

    </div>
</body>

<script>


//MESSAGE FEEDBACK FORM
document.getElementById("feedbackForm").addEventListener("submit", function (e) {
  e.preventDefault(); // Prevent normal form submission

  const formData = new FormData(this);

  fetch("feedback-to-database.php", {
    method: "POST",
    body: formData
  })
  .then(response => response.text())
  .then(data => {
    // You can log data if needed
    // console.log(data);

    // Show thank-you modal
    document.getElementById("feedbackForm").reset();
    showModal();
  })
  .catch(error => {
    console.error("Submission error:", error);
    alert("Something went wrong. Please try again.");
  });
});

function showModal() {
  document.getElementById("thankYouModal").style.display = "flex";
}

function closeModal() {
  document.getElementById("thankYouModal").style.display = "none";
}

//STAR RATING FORM

document.getElementById("starRating").addEventListener("submit", function(e) {
  e.preventDefault();

  const formData = new FormData(this);

  fetch("submit-rating.php", {
    method: "POST",
    body: formData
  })
  .then(res => res.text())
  .then(data => {
    document.getElementById("starRating").reset();
    showStarModal();
  })
  .catch(err => {
    console.error("Error submitting rating:", err);
    alert("Something went wrong.");
  });
});

function showStarModal() {
  document.getElementById("starThankYouModal").style.display = "flex";
}

function closeStarModal() {
  document.getElementById("starThankYouModal").style.display = "none";
}

history.pushState(null, null, location.href);
window.onpopstate = function () {
    history.go(1);
};


</script>

</html>
