(function (window, document, drupalSettings, Drupal) {

  Drupal.behaviors.GameTimer = {
    attach: function () {
      var whiteTimer = new GameTimer('js-white-timer');
      var blackTimer = new GameTimer('js-black-timer');
      whiteTimer.initialize();
      blackTimer.initialize();
      if (drupalSettings.vchess.active_timer === 'w') {
        whiteTimer.start();
        activeTimerId = "js-white-timer";
      }
      else if (drupalSettings.vchess.active_timer === 'b') {
        blackTimer.start();
        activeTimerId = "js-black-timer";
      }
    }
  };

  GameTimer = function (elementId) {
    this.element = document.getElementById(elementId).getElementsByTagName('span')[0];
    this.hiddenElement = document.getElementById(elementId).getElementsByTagName('input')[0];
    this.timerId = null;
    this.ura = null;
  };

  GameTimer.prototype.initialize = function() {
    var timeLeft = parseInt(this.element.dataset.timeLeft);
    var hoursLeft = Math.max(Math.floor(timeLeft / 3600), 0);
    var minutesLeft = Math.max(Math.floor((timeLeft % 3600) / 60), 0);
    var secondsLeft = Math.max(Math.floor(timeLeft % 60), 0);

    this.ura = new Date();
    this.ura.setHours(this.ura.getHours() + hoursLeft);
    this.ura.setMinutes(this.ura.getMinutes() + minutesLeft);
    this.ura.setSeconds(this.ura.getSeconds() + secondsLeft);

    this.displayTimeLeft(timeLeft);
  };

  GameTimer.prototype.start = function () {
    this.timerId = window.setInterval(this.updateTimeLeft.bind(this), 1000);
  };

  GameTimer.prototype.updateTimeLeft = function() {
    var now = new Date();
    var diff = this.ura - now;
    this.displayTimeLeft(diff / 1000);
    if (now >= this.ura) {
      window.clearInterval(this.timerId);
    }
  };

  GameTimer.prototype.displayTimeLeft = function (timeLeft) {
    var hrs = Math.max(Math.floor(timeLeft / 3600), 0);
    var min = Math.max(Math.floor((timeLeft % 3600) / 60), 0);
    var sec = Math.max(Math.floor(timeLeft % 60), 0);

    if (hrs < 10) {
      hrs = "0" + hrs;
    }
    if (min < 10) {
      min = "0" + min;
    }
    if (sec < 10) {
      sec = "0" + sec;
    }

    // Update display.
    this.element.innerHTML = hrs + ":" + min + ":" + sec;

    // Update hidden element.
    this.hiddenElement.value = timeLeft;
  }


})(window, window.document, drupalSettings, Drupal);
