var webSocket

//On load, set up web socket connection to handle real time events
onload = function () {
    if (!("WebSocket" in window)) {
        alert("Error: Your web browser is not supported");
    }

    //Set Controls disabled by default
    setControlsDisabled(true);

    //Get the current url to set up the web socket
    webSocket = new WebSocket("ws://" + location.host + ":8000");

    webSocket.onopen = function (connection) {
        document.getElementById("VideoDisplay").src = "http://10.100.143.53:8081";
        document.getElementById("connectionFeedback").innerHTML = "Connected";
        document.getElementById("connectionFeedback").style = null;
        document.getElementById("requestButton").disabled = false
    };

    webSocket.onmessage = function (event) {
        var json = JSON.parse(event.data);

        for (key in json) {
            switch (key) {
                case 'defVoltage':
                    document.getElementById("defVoltageSlider").value = json[key];
                    document.getElementById("defVoltageValue").innerHTML = json[key] + " Volts"; //Cannot be 1 so always append the plural form
                    break;
                case 'accVoltage':
                    document.getElementById("accVoltageSlider").value = json[key];
                    document.getElementById("accVoltageValue").innerHTML = json[key] + " Volt" + ((json[key] == 1) ? "" : "s");
                    break;
                case 'currentAmperage':
                    document.getElementById("currentSlider").value = json[key] / 100.0;
                    document.getElementById("currentValue").innerHTML = json[key] / 100.0 + " Ampere" + ((json[key] / 100.0 == 1) ? "" : "s");
                    break;
                case 'defVoltagePolarity':
                    document.getElementsByName('defVoltagePolarity')[json[key]].checked = true;
                    break;
                case 'magneticArc':
                    document.getElementsByName('magneticArc')[json[key]].checked = true;
                    break;
                case 'computersBefore':
                    document.getElementById('computersBefore').innerHTML =
                        (json[key] > 0) ? json[key] + " Computers Before You<br/>" : "You are next!<br/>";
                    break;
                case 'computersWaiting':
                    document.getElementById('computersWaiting').innerHTML = json[key] + " Computers Waiting";
                    break;
                case 'computersConnected':
                    document.getElementById('computersConnected').innerHTML = json[key] + " Computers Connected";
                    break;
                case 'controllingId':
                    document.getElementById('controllingId').innerHTML = json[key] + " Is In Control Of The Device";
                    break;
                case 'controlDuration':
                    document.getElementById('controlDurationSlider').value = Math.floor(json[key] / 30000); //Convert from milliseconds
                    document.getElementById("controlDuration").innerHTML = sliderValueToTimeString(Math.floor(json[key] / 30000));
                    break;
                case 'setControlDurationSliderDisabled':
                    document.getElementById('controlDurationSlider').disabled = json[key];
                    break;
                case 'ownId':
                    document.getElementById('ownId').innerHTML = "Your Computer ID is " + json[key];
                    break;
                case 'setControlsDisabled':
                    setControlsDisabled(json[key]);
                    if (!json[key]) {
                        alert("Access Granted!");
                        document.getElementById('computersBefore').innerHTML = "You have access!<br/>";
                    }
                    break;
                case 'setControlDuration':
                    setTimeout(function () { returnControl() }, json[key]);
                    break;
                case 'error':
                    alert(json[key]);
                    break;
                default:
                    alert("Error: Unable to use message from server!");
            }
        }
    };

    webSocket.onclose = function () {
        document.getElementById("VideoDisplay").src = null;
        document.getElementById("connectionFeedback").innerHTML = "Not Connected";
        document.getElementById("connectionFeedback").style = "color:red;";
        webSocket = null;
    };
}

// Set all controls enabled or disabled
function setControlsDisabled(isDisabled) {
    document.getElementById("defVoltageSlider").disabled = isDisabled;
    document.getElementById("accVoltageSlider").disabled = isDisabled;
    document.getElementById("currentSlider").disabled = isDisabled;

    for (var iii = 0; iii < 3; iii++) {
        document.getElementsByName("defVoltagePolarity")[iii].disabled = isDisabled;
        document.getElementsByName("magneticArc")[iii].disabled = isDisabled;
    }
}

function returnControl() {
    webSocket.send(JSON.stringify({ returnControl: null }));
    document.getElementById("requestButton").disabled = false;
    document.getElementById("requestButton").textContent = "Request Access";
    document.getElementById("computersBefore").innerHTML = "<br/>";
    alert("Returning Control");
}

// Handle defecting voltage slider moved
function onDefVoltageSliderChanged(slider) {
    //Range 50 to 250 volts
    document.getElementById("defVoltageValue").innerHTML = slider.value + " Volts";
    webSocket.send(JSON.stringify({ defVoltage: slider.value }));
}

// Handle accelerating voltage slider moved
function onAccVoltageSliderChanged(slider) {
    //Range 0 to 250 volts
    document.getElementById("accVoltageValue").innerHTML = slider.value + " Volt" + ((slider.value == 1.0) ? "" : "s");
    webSocket.send(JSON.stringify({ accVoltage: slider.value }));
}

// Handle current slider moved
function onCurrentSliderChanged(slider) {
    //Range 0 to 3.0 amps
    document.getElementById("currentValue").innerHTML = slider.value + " Ampere" + ((slider.value == 1) ? "" : "s");
    //Convert amperage from decimal to integer for database storage
    webSocket.send(JSON.stringify({ currentAmperage: slider.value * 100 }));
}

// Handle deflecting voltage polarity changed
function onDefVoltagePolarityChanged(radio) {
    webSocket.send(JSON.stringify({ defVoltagePolarity: radio.value }));
}

// Handle current direction changed
function onMagneticArcChanged(radio) {
    webSocket.send(JSON.stringify({ magneticArc: radio.value }));
}

// Slider range is 1 to 20. Each int represents 30 seconds
function sliderValueToTimeString(value) {
    var duration = Math.floor(value / 2); //int division

    if (value % 2 == 1) {
        duration += ":30";
    } else {
        duration += ":00";
    }

    return duration;
}

// Handle control duration changed
function onControlDurationChanged(slider) {
    document.getElementById("controlDuration").innerHTML = sliderValueToTimeString(slider.value);
    webSocket.send(JSON.stringify({ setControlDuration: slider.value * 30000 })); //Convert to milliseconds for server
}

// Handle access request
function onRequestAccessPressed(button) {
    document.getElementById("requestButton").disabled = true;
    document.getElementById("requestButton").innerHTML = "Requested";
    webSocket.send(JSON.stringify({ requestAccess: "" }));
}
