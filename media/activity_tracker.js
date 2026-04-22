// Send activity update to server every 5 minutes
const ACTIVITY_UPDATE_INTERVAL = 5 * 60 * 1000; // 5 minutes

function sendActivityUpdate() {
    fetch('api/update-activity.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        }
    }).catch(err => console.log('Activity update failed'));
}

// Track user activity events
const activityEvents = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];

activityEvents.forEach(event => {
    document.addEventListener(event, sendActivityUpdate, { once: true });
});

// Send activity update periodically
setInterval(sendActivityUpdate, ACTIVITY_UPDATE_INTERVAL);

// Send initial activity update
sendActivityUpdate();