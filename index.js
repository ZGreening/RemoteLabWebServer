// Get initial values when page loads
onload = function () {
    updateDefVoltage();
    updateAccVoltage();
    updateCurrentAmperage();
    updateMagneticArc();
    updateDefVoltagePolarity();
    updateComputersWaiting();
}

// Handle defecting voltage slider moved
function onDefVoltageSliderChanged(slider) {
    //Range 50 to 250 volts
    document.getElementById("defVoltageValue").innerHTML = slider.value + " Volts"; 

    $.post('database.php', { function: 'setDefVoltage', arguments: [slider.value]
    }, function (response) { if ('error' in response) alert(response['error']) });
}

// Handle accelerating voltage slider moved
function onAccVoltageSliderChanged(slider) {
    //Range 0 to 250 volts
    document.getElementById("accVoltageValue").innerHTML = slider.value + " Volt" + ((slider.value == 1.0) ? "" : "s");

    $.post('database.php', { function: 'setAccVoltage', arguments: [slider.value]
    }, function (response) { if ('error' in response) alert(response['error']) });
}

// Handle current slider moved
function onCurrentSliderChanged(slider) {
    //Range 0 to 3.0 amps
    document.getElementById("currentValue").innerHTML = slider.value + " Ampere" + ((slider.value == 1) ? "" : "s"); 

    //Convert amperage from decimal to integer for database storage
    $.post('database.php', { function: 'setCurrentAmperage', arguments: [slider.value * 100]
    }, function (response) { if ('error' in response) alert(response['error']) });
}

// Handle deflecting voltage polarity changed
function onDefVoltagePolarityChanged(radio) {
    $.post('database.php', { function: 'setDefVoltagePolarity', arguments: [radio.value]
    }, function (response) { if ('error' in response) alert(response['error']) });
}

// Handle current direction changed
function onMagneticArcChanged(radio) {
    $.post('database.php', { function: 'setMagneticArc', arguments: [radio.value]
    }, function (response) { if ('error' in response) alert(response['error']) });
}

// Handle access request
function onRequestAccessPressed(button) {
    document.getElementById("requestButton").disabled=true;
    document.getElementById("requestButton").innerHTML="Requested";

    $.get('database.php', {function: 'requestAccess'},
    function (response) { if ('error' in response) alert(response['error']) });
}







//Update the page with deflecting voltage from the database
function updateDefVoltage() {
    $.get('database.php', { function: 'getDefVoltage' }, function (data) {
        if ('value' in data) {
            document.getElementById("defVoltageSlider").value = data['value']; //May cause redundant setDef. network call
            document.getElementById("defVoltageValue").innerHTML = data['value'] + " Volts"; //Cannot be 1 so always append the plural form
        }
    });
}

//Update the page with accelerating voltage from the database
function updateAccVoltage() {
    $.get('database.php', { function: 'getAccVoltage' }, function (data) {
        if ('value' in data) {
            document.getElementById("accVoltageSlider").value = data['value']; //May cause redundant setDef. network call
            document.getElementById("accVoltageValue").innerHTML = data['value'] + " Volt" + ((data['value'] == 1) ? "" : "s");
        }
    });
}

//Update the page with current from the database
function updateCurrentAmperage() {
    $.get('database.php', { function: 'getCurrentAmperage' }, function (data) {
        if ('value' in data) {
            //Convert amperage from integer to decimal for display
            document.getElementById("currentSlider").value = data['value'] / 100.0; //May cause redundant setDef. network call
            document.getElementById("currentValue").innerHTML = data['value'] / 100.0 + " Ampere" + ((data['value'] / 100.0 == 1) ? "" : "s");
        }
    });
}

function updateDefVoltagePolarity() {
    $.get('database.php', { function: 'getDefVoltagePolarity' }, function (data) {
        if ('value' in data) {
            document.getElementsByName('defVoltagePolarity')[data['value']].checked = true;
        }
    });
}

function updateMagneticArc() {
    $.get('database.php', { function: 'getMagneticArc' }, function (data) {
        if ('value' in data) {
            document.getElementsByName('magneticArc')[data['value']].checked = true;
        }
    });
}

function updateComputersWaiting() {
    $.get('database.php', { function: 'getNumberComputersWaiting' }, function (data) {
        if ('value' in data) {
            document.getElementById('computersWaiting').innerHTML = data['value'] + " Computers Waiting";
        }
    });
}
