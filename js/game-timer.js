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
      else {
        blackTimer.start();
        activeTimerId = "js-black-timer";
      }
    }
  };

  GameTimer = function (elementId) {
    this.element = document.getElementById(elementId);
    this.timerId = null;
    this.ura = null;
  };

  GameTimer.prototype.initialize = function() {
    var timeLeft = parseInt(this.element.dataset.timeLeft);
    var hoursLeft = Math.floor(timeLeft / 3600);
    var minutesLeft = Math.floor((timeLeft % 3600) / 60);
    var secondsLeft = Math.floor(timeLeft % 60);

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
    var hrs = Math.floor(timeLeft / 3600);
    var min = Math.floor((timeLeft % 3600) / 60);
    var sec = Math.floor(timeLeft % 60);

    if (min < 10) {
      min = "0" + min;
    }
    if (sec < 10) {
      sec = "0" + sec;
    }
    this.element.innerHTML = min + ":" + sec;
  }


})(window, window.document, drupalSettings, Drupal);
