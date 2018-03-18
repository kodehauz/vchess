(function (window, document, drupalSettings, Drupal) {

  Drupal.behaviors.GameTimer = {
    attach: function () {
      const whiteTimer = new GameTimer('js-white-timer');
      const blackTimer = new GameTimer('js-black-timer');
      whiteTimer.initialize();
      blackTimer.initialize();
      // Create the timer handler if it's the first time.
      if (window.activeTimerId === undefined) {
        window.setInterval(function() {
          whiteTimer.updateTimeLeft();
          blackTimer.updateTimeLeft();
        }, 1000);
      }
      if (drupalSettings.vchess.active_timer === 'w') {
        whiteTimer.enable();
        window.activeTimerId = "js-white-timer";
      }
      else if (drupalSettings.vchess.active_timer === 'b') {
        blackTimer.enable();
        window.activeTimerId = "js-black-timer";
      }
    }
  };

  GameTimer = function (elementId) {
    this.element = document.getElementById(elementId).getElementsByTagName('span')[0];
    this.hiddenElement = document.getElementById(elementId).getElementsByTagName('input')[0];
    this.timerId = null;
    this.ura = null;
    this.enabled = false;
  };

  GameTimer.prototype.initialize = function() {
    const timeLeft = parseInt(this.element.dataset.timeLeft);
    const hoursLeft = Math.max(Math.floor(timeLeft / 3600), 0);
    const minutesLeft = Math.max(Math.floor((timeLeft % 3600) / 60), 0);
    const secondsLeft = Math.max(Math.floor(timeLeft % 60), 0);

    this.ura = new Date();
    this.ura.setHours(this.ura.getHours() + hoursLeft);
    this.ura.setMinutes(this.ura.getMinutes() + minutesLeft);
    this.ura.setSeconds(this.ura.getSeconds() + secondsLeft);

    this.displayTimeLeft(timeLeft);
  };

  GameTimer.prototype.enable = function () {
    this.enabled = true;
  };

  GameTimer.prototype.updateTimeLeft = function() {
    // Do nothing if not enabled.
    if (!this.enabled) {
      return;
    }

    const now = new Date();
    const diff = this.ura - now;
    this.displayTimeLeft(diff / 1000);
    if (now >= this.ura) {
      window.clearInterval(this.timerId);
    }
    // Post time updates.
    const form = document.getElementsByClassName('vchess-game-form')[0];
    // Create the submit object and add the timers.
    let submit = {}, name, index;
    for (index in drupalSettings.vchess.game_timers) {
      if (drupalSettings.vchess.game_timers.hasOwnProperty(index)) {
        name = drupalSettings.vchess.game_timers[index];
        submit[name] = {
          white: form[name + '[white]'].value,
          black: form[name + '[black]'].value,
        };
      }
    }
    submit['js'] = true;
    submit['form_build_id'] = form['form_build_id'].value;
    submit['form_token'] = form['form_token'].value;
    submit['form_id'] = form['form_id'].value;

    // Use Drupal's ajax API to post the updates.
    const time_update = {
      url: drupalSettings.vchess.gametimer_url,
      progress: false,
      submit: submit,
    };
    Drupal.ajax(time_update).execute();
  };

  GameTimer.prototype.displayTimeLeft = function (timeLeft) {
    let hrs = Math.max(Math.floor(timeLeft / 3600), 0);
    let min = Math.max(Math.floor((timeLeft % 3600) / 60), 0);
    let sec = Math.max(Math.floor(timeLeft % 60), 0);

    if (hrs < 10) hrs = "0" + hrs;
    if (min < 10) min = "0" + min;
    if (sec < 10) sec = "0" + sec;

    // Update display.
    this.element.innerHTML = hrs + ":" + min + ":" + sec;

    // Update hidden element.
    this.hiddenElement.value = timeLeft;
  }


})(window, window.document, drupalSettings, Drupal);
