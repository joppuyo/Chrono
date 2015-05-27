$(function(){

    if (localStorage.active === "true") {
        startCounter();
    }

    $("#circle").click(function(){
        localStorage.active = "true";
        var dateTime = moment();
        alert(dateTime);
        localStorage.setItem("startTime", dateTime.format());
        startCounter();
    });

    function startCounter(){
        updateCounter();
        setInterval(updateCounter, 1000);
        $("#circle").addClass("circle-active");
    }

    function updateCounter(){
        var startDateTime = moment(localStorage.startTime);
        var nowDateTime = moment();
        var secs = nowDateTime.diff(startDateTime, "seconds");
        $("#circle").text(secs + "s");
    }

});