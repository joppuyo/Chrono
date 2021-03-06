$(function(){

    var counterInterval;

    countdown.setLabels(
        'ms|s|m|h|d|w|m|y|||',
        'ms|s|m|h|d|w|m|y|||',
        '',
        '',
        '0s');

    if (localStorage.active === "true") {
        startCounter();
    }

    $("#circle").click(function(){
        if (localStorage.active !== "true") {
            localStorage.active = "true";
            var dateTime = moment();
            localStorage.setItem("startTime", dateTime.format());
            startCounter();
        }
    });

    function startCounter(){
        updateCounter();
        counterInterval = setInterval(updateCounter, 1000);
        $("#circle").addClass("circle-active");
        $("#action-finish").toggleClass("hidden");
    }

    function stopCounter(){
        $("#circle").removeClass("circle-active");
        $("#circle").text("Start");
        $("#action-finish").toggleClass("hidden");
        localStorage.active = "false";
        clearInterval(counterInterval);
    }

    function updateCounter(){
        var startDateTime = moment(localStorage.startTime);
        var time = countdown( startDateTime.toDate() ).toString();
        $("#circle").text(time);
    }

    $("#action-finish").click(function(){
        var data = {};
        data.start_datetime = moment(localStorage.startTime).format("YYYY-MM-DD HH:mm:ss");
        data.end_datetime = moment().format("YYYY-MM-DD HH:mm:ss");
        console.log(data);
        $.post(apiRoot + "time/", JSON.stringify(data), function(){
        });
        stopCounter();
    });

});