<?php

return [
    'labels' => ['today_complete' => 'Lecture du jour terminée', 'start' => 'Commencer une série', 'at_risk' => 'Série en danger', 'not_read' => 'Aucune lecture aujourd’hui'],
    'records' => [
        'tied' => '{1} Vous avez égalé votre meilleure série de :days jour. Lisez demain pour établir un nouveau record.|[2,*] Vous avez égalé votre meilleure série de :days jours. Lisez demain pour établir un nouveau record.',
        'record' => '{1} Nouveau record! Votre série de :days jour devient celle à battre.|[2,*] Nouveau record! Votre série de :days jours devient celle à battre.',
    ],
    'messages' => [
        'inactive' => [
            'Aujourd’hui est un bon jour pour commencer votre lecture.',
            'Faites le premier pas vers une habitude de lecture.',
            'Commencez votre parcours de lecture dès aujourd’hui.',
            'Votre prochaine lecture peut commencer maintenant.',
        ],
        'milestone' => [
            '{1} Belle étape : :days jour de lecture consécutifs!|[2,*] Belle étape : :days jours de lecture consécutifs!',
            '{1} Vous avez lu :days jour de suite. Continuez ainsi!|[2,*] Vous avez lu :days jours de suite. Continuez ainsi!',
            '{1} Votre série atteint :days jour. Bravo pour votre constance!|[2,*] Votre série atteint :days jours. Bravo pour votre constance!',
            '{1} :days jour de lecture consécutifs : une belle réussite!|[2,*] :days jours de lecture consécutifs : une belle réussite!',
        ],
        'active' => [
            '{1} Votre série de :days jour prend de l’élan!|[2,*] Votre série de :days jours prend de l’élan!',
            '{1} Vous lisez depuis :days jour consécutif. Gardez ce rythme!|[2,*] Vous lisez depuis :days jours consécutifs. Gardez ce rythme!',
            '{1} Belle constance : :days jour de lecture de suite!|[2,*] Belle constance : :days jours de lecture de suite!',
            '{1} Chaque jour compte. Vous en êtes à :days jour consécutif!|[2,*] Chaque jour compte. Vous en êtes à :days jours consécutifs!',
        ],
        'warning' => [
            '{1} Ne laissez pas votre série de :days jour s’interrompre. Lisez aujourd’hui!|[2,*] Ne laissez pas votre série de :days jours s’interrompre. Lisez aujourd’hui!',
            '{1} Votre série de :days jour compte sur vous!|[2,*] Votre série de :days jours compte sur vous!',
            '{1} Poursuivez votre série de :days jour avec une lecture aujourd’hui!|[2,*] Poursuivez votre série de :days jours avec une lecture aujourd’hui!',
            '{1} Gardez les acquis de votre :days jour de lecture. Lisez aujourd’hui!|[2,*] Gardez les acquis de vos :days jours de lecture. Lisez aujourd’hui!',
            '{1} Une lecture aujourd’hui préservera votre série de :days jour!|[2,*] Une lecture aujourd’hui préservera votre série de :days jours!',
        ],
        'acknowledge' => [
            'Bravo, vous avez lu aujourd’hui!',
            'Belle constance, votre lecture du jour est faite!',
            'Votre série est préservée pour aujourd’hui!',
            'Un autre jour de lecture accompli!',
        ],
    ],
];
