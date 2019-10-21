// Deflecting Voltage
var defVoltageSlider = document.getElementById("volt-def-slider");
var defVoltageValue = document.getElementById("volt-def-value");

// Accelerating Voltage
var accVoltageSlider = document.getElementById("volt-acc-slider");
var accVoltageValue = document.getElementById("volt-acc-value");

// Current
var currentSlider = document.getElementById("curr-slider");
var currentValue = document.getElementById("curr-value");

// Get initial values when page loads
onload = function () {
    updateDeflectingVoltage();
    updateAcceleratingVoltage();
    updateCurrentAmperage();
    updateMagneticArc();
    updateDefVoltagePolarity();
}

// handle defecting voltage slider moved
function onDefVoltageSliderChanged(slider) {
    defVoltageValue.innerHTML = slider.value + " Volts"; //Range 50 to 250 volts
    $.post('database.php', {
        function: 'setDefVoltage', arguments: [slider.value]
    }, function (response) { if ('error' in response) alert(response['error']) });
}

// Handle accelerating voltage slider moved
function onAccVoltageSliderChanged(slider) {
    accVoltageValue.innerHTML = slider.value + " Volt" + ((slider.value == 1.0) ? "" : "s"); //Range 0 to 250 volts
    $.post('database.php', {
        function: 'setAccVoltage', arguments: [slider.value]
    }, function (response) { if ('error' in response) alert(response['error']) });
}

// Handle current slider moved
function onCurrentSliderChanged(slider) {
    currentValue.innerHTML = slider.value + " Ampere" + ((slider.value == 1) ? "" : "s"); //Range 0 to 3.0 amps
    //Convert amperage from decimal to integer for database storage
    $.post('database.php', {
        function: 'setCurrentAmperage', arguments: [slider.value * 100]
    }, function (response) { if ('error' in response) alert(response['error']) });
}

// Handle deflecting voltage polarity changed
function onDefVoltagePolarityChanged(radio) {
    $.post('database.php', {
        function: 'setDefVoltagePolarity', arguments: [radio.value]
    }, function (response) { if ('error' in response) alert(response['error']) });
}

// Handle current direction changed
function onMagneticArcChanged(radio) {
    $.post('database.php', {
        function: 'setMagneticArc', arguments: [radio.value]
    }, function (response) { if ('error' in response) alert(response['error']) });
}







//Update the page with deflecting voltage from the database
function updateDeflectingVoltage() {
    $.get('database.php', { function: 'getDefVoltage' }, function (data) {
        if ('value' in data) {
            defVoltageSlider.value = data['value']; //May cause redundant setDef. network call
            defVoltageValue.innerHTML = data['value'] + " Volts"; //Cannot be 1 so always append the plural form
        }
    });
}

//Update the page with accelerating voltage from the database
function updateAcceleratingVoltage() {
    $.get('database.php', { function: 'getAccVoltage' }, function (data) {
        if ('value' in data) {
            accVoltageSlider.value = data['value']; //May cause redundant setDef. network call
            accVoltageValue.innerHTML = data['value'] + " Volt" + ((data['value'] == 1) ? "" : "s");
        }
    });
}

//Update the page with current from the database
function updateCurrentAmperage() {
    $.get('database.php', { function: 'getCurrentAmperage' }, function (data) {
        if ('value' in data) {
            //Convert amperage from integer to decimal for display
            currentSlider.value = data['value'] / 100.0; //May cause redundant setDef. network call
            currentValue.innerHTML = data['value'] / 100.0 + " Ampere" + ((data['value'] / 100.0 == 1) ? "" : "s");
        }
    });
}

function updateDefVoltagePolarity() {
    $.get('database.php', { function: 'getDefVoltagePolarity' }, function (data) {
        if ('value' in data) {
            //Check the appropriate radio button
            document.getElementsByName('voltage-polarity')[data['value']].checked = true; 
        }
    });
}

function updateMagneticArc() {
    $.get('database.php', { function: 'getMagneticArc' }, function (data) {
        if ('value' in data) {
            //Check the appropriate radio button
            document.getElementsByName('magnetic-arc')[data['value']].checked = true;
        }
    });
}
