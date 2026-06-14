(function (global) {
    'use strict';

    var defaults = {
        skills: true,
        improve: true,
        evolve: true,
        recycle: true,
        trainingCombat: true,
        dailyBoss: true,
        dungeon: false,
        gauntlet: false
    };

    global.HG_CARD_GAME_FEATURES = Object.assign({}, defaults, global.HG_CARD_GAME_FEATURES || {});
})(window);
