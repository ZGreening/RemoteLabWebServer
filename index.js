var webSocket 

//On load, set up web socket connection to handle real time events
onload = function () {
    if (!("WebSocket" in window)) {
        alert("Error: Your web browser is not supported");
    }

    //Get the current url to set up the web socket
    webSocket = new WebSocket("ws://" + location.host + ":8000");

    webSocket.onopen = function () {
        document.getElementById("connectedFeedback").innerHTML = "Connected";
        document.getElementById("connectedFeedback").style = null;
    };

    webSocket.onmessage = function (event) {
        var json = JSON.parse(event.data);

        for(key in json) {
            switch(key) {
            case 'defVoltage':
                document.getElementById("defVoltageSlider").value = json[key]; //May cause redundant setDef. network call
                document.getElementById("defVoltageValue").innerHTML = json[key] + " Volts"; //Cannot be 1 so always append the plural form
                break;
            case 'accVoltage':
                document.getElementById("accVoltageSlider").value = json[key]; //May cause redundant setDef. network call
                document.getElementById("accVoltageValue").innerHTML = json[key] + " Volt" + ((json[key] == 1) ? "" : "s");
                break;
            case 'currentAmperage':
                document.getElementById("currentSlider").value = json[key] / 100.0; //May cause redundant setDef. network call
                document.getElementById("currentValue").innerHTML = json[key] / 100.0 + " Ampere" + ((json[key] / 100.0 == 1) ? "" : "s");
                break;
            case 'defVoltagePolarity':
                document.getElementsByName('defVoltagePolarity')[json[key]].checked = true;
                break;
            case 'magneticArc':
                document.getElementsByName('magneticArc')[json[key]].checked = true;
                break;
            case 'computersWaiting':
                document.getElementById('computersWaiting').innerHTML = json[key] + " Computers Waiting";
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
        document.getElementById("connectedFeedback").innerHTML = "Not Connected";
        document.getElementById("connectedFeedback").style = "color:red;";
        webSocket=null;
    };
}

// Handle defecting voltage slider moved
function onDefVoltageSliderChanged(slider) {
    //Range 50 to 250 volts
    document.getElementById("defVoltageValue").innerHTML = slider.value + " Volts";
    webSocket.send(JSON.stringify({defVoltage: slider.value}));
}

// Handle accelerating voltage slider moved
function onAccVoltageSliderChanged(slider) {
    //Range 0 to 250 volts
    document.getElementById("accVoltageValue").innerHTML = slider.value + " Volt" + ((slider.value == 1.0) ? "" : "s");
    webSocket.send(JSON.stringify({accVoltage: slider.value}));
}

// Handle current slider moved
function onCurrentSliderChanged(slider) {
    //Range 0 to 3.0 amps
    document.getElementById("currentValue").innerHTML = slider.value + " Ampere" + ((slider.value == 1) ? "" : "s");
    //Convert amperage from decimal to integer for database storage
    webSocket.send(JSON.stringify({currentAmperage: slider.value*100}));
}

// Handle deflecting voltage polarity changed
function onDefVoltagePolarityChanged(radio) {
    webSocket.send(JSON.stringify({defVoltagePolarity: radio.value}));
}

// Handle current direction changed
function onMagneticArcChanged(radio) {
    webSocket.send(JSON.stringify({magneticArc: radio.value}));
}

// Handle access request
function onRequestAccessPressed(button) {
    document.getElementById("requestButton").disabled = true;
    document.getElementById("requestButton").innerHTML = "Requested";
    webSocket.send(JSON.stringify({"requestAccess": ""}));
}
