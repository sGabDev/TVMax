<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>TVMAX - Transmissão Pausada</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <style>
        :root {
            --bg: #030814;
            --bg2: #071321;

            --cyan: #00d9ff;
            --cyan2: #4deeff;

            --red: #ff445c;

            --text: #ffffff;
            --sub: #8aa4b7;

            --glass: rgba(255, 255, 255, .03);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html,
        body {
            width: 100%;
            height: 100%;
            overflow: hidden;
            font-family: 'Inter', sans-serif;
            background: #000;
        }

        body {

            background:
                radial-gradient(circle at center,
                    rgba(0, 180, 255, .15),
                    transparent 30%),
                radial-gradient(circle at top,
                    rgba(0, 255, 255, .05),
                    transparent 50%),
                linear-gradient(180deg,
                    #07111d 0%,
                    #030814 50%,
                    #01050a 100%);

            color: white;
        }


        /* ======================================================
   GRID FUTURISTA
====================================================== */

        .grid {
            position: absolute;
            inset: -50%;

            background-image:
                linear-gradient(rgba(0, 180, 255, .07) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 180, 255, .07) 1px, transparent 1px);

            background-size: 70px 70px;

            transform:
                perspective(800px) rotateX(70deg) translateY(35%);

            animation: gridMove 12s linear infinite;

            opacity: .4;
        }

        @keyframes gridMove {
            from {
                background-position: 0 0;
            }

            to {
                background-position: 0 70px;
            }
        }


        /* ======================================================
   PARTÍCULAS
====================================================== */

        .particles {
            position: absolute;
            inset: 0;
        }

        .particles span {
            position: absolute;

            width: 2px;
            height: 2px;

            border-radius: 50%;

            background: var(--cyan);

            box-shadow:
                0 0 10px var(--cyan),
                0 0 20px var(--cyan);

            animation: particle linear infinite;
        }

        @keyframes particle {

            0% {
                transform: translateY(50px);
                opacity: 0;
            }

            20% {
                opacity: .8;
            }

            100% {
                transform: translateY(-150px);
                opacity: 0;
            }
        }


        /* ======================================================
   HEADER
====================================================== */

        .top {

            position: absolute;
            top: 40px;
            left: 50%;

            transform: translateX(-50%);

            width: 90%;
            max-width: 1500px;

            display: flex;
            justify-content: space-between;
            align-items: center;

            z-index: 10;
        }

        .brand {

            display: flex;
            align-items: center;
            gap: 20px;
        }

        .logo {

            width: 70px;
            height: 70px;

            border-radius: 20px;

            background:
                linear-gradient(145deg,
                    rgba(0, 255, 255, .2),
                    rgba(0, 150, 255, .1));

            border: 1px solid rgba(0, 255, 255, .25);

            display: flex;
            align-items: center;
            justify-content: center;

            backdrop-filter: blur(10px);

            box-shadow:
                0 0 40px rgba(0, 200, 255, .2);
        }

        .logo img {
            width: 45px;
        }

        .brand-text h3 {
            font-size: 26px;
            letter-spacing: 4px;
        }

        .brand-text p {
            color: #6e8798;
            letter-spacing: 3px;
            font-size: 11px;
        }


        /* ======================================================
   LIVE
====================================================== */

        .live {

            display: flex;
            align-items: center;
            gap: 12px;

            padding: 12px 20px;

            border-radius: 40px;

            background: rgba(255, 40, 60, .08);

            border: 1px solid rgba(255, 60, 80, .3);

            backdrop-filter: blur(15px);

            font-size: 12px;
            letter-spacing: 3px;
        }

        .live-dot {

            width: 10px;
            height: 10px;

            border-radius: 50%;

            background: var(--red);

            box-shadow:
                0 0 10px var(--red),
                0 0 30px var(--red);

            animation: livePulse 1.4s infinite;
        }

        @keyframes livePulse {
            50% {
                transform: scale(.6);
                opacity: .4;
            }
        }


        /* ======================================================
   CENTRO
====================================================== */

        .center {

            position: absolute;
            left: 50%;
            top: 50%;

            transform: translate(-50%, -50%);

            display: flex;
            flex-direction: column;
            align-items: center;

            text-align: center;
        }


        /* ======================================================
   HOLOGRAMA
====================================================== */

        .hologram {

            position: relative;

            width: 250px;
            height: 250px;

            display: flex;
            align-items: center;
            justify-content: center;
        }

        .core-glow {

            position: absolute;

            width: 140px;
            height: 140px;

            background: var(--cyan);

            border-radius: 50%;

            filter: blur(60px);

            opacity: .3;

            animation: glow 3s ease infinite alternate;
        }

        @keyframes glow {
            from {
                transform: scale(.9);
            }

            to {
                transform: scale(1.3);
            }
        }

        .core {

            width: 120px;
            height: 120px;

            border-radius: 50%;

            background:
                radial-gradient(circle at 30% 30%,
                    rgba(255, 255, 255, .3),
                    rgba(0, 160, 255, .15),
                    rgba(0, 0, 0, .8));

            border: 1px solid rgba(0, 255, 255, .4);

            display: flex;
            justify-content: center;
            align-items: center;

            backdrop-filter: blur(20px);

            box-shadow:
                0 0 50px rgba(0, 200, 255, .3);
        }

        .pause {
            display: flex;
            gap: 10px;
        }

        .pause span {

            width: 12px;
            height: 40px;

            border-radius: 6px;

            background: linear-gradient(to bottom,
                    white,
                    var(--cyan));

            box-shadow: 0 0 15px var(--cyan);
        }


        /* ORBITAS */

        .orbit {

            position: absolute;

            width: 240px;
            height: 90px;

            border-radius: 50%;

            border: 1px solid rgba(0, 255, 255, .25);

            animation: spin 8s linear infinite;
        }

        .orbit2 {
            transform: rotate(60deg);
            animation-duration: 12s;
        }

        .orbit3 {
            transform: rotate(120deg);
            animation-duration: 16s;
        }

        @keyframes spin {
            from {
                transform: rotateX(70deg) rotateZ(0deg);
            }

            to {
                transform: rotateX(70deg) rotateZ(360deg);
            }
        }


        /* ======================================================
   TEXTO
====================================================== */

        .tag {

            margin-top: 30px;

            color: var(--cyan);

            letter-spacing: 10px;

            font-size: 12px;
        }

        h1 {

            font-size: 90px;
            margin-top: 20px;

            font-weight: 800;

            text-shadow:
                0 0 30px rgba(255, 255, 255, .15);
        }

        .badge {

            margin-top: 25px;

            display: flex;
            align-items: center;
            gap: 12px;

            padding: 12px 30px;

            border-radius: 40px;

            background: rgba(255, 50, 80, .08);

            border: 1px solid rgba(255, 50, 80, .25);

            color: #ff7687;

            letter-spacing: 4px;
        }

        .badge-dot {

            width: 8px;
            height: 8px;

            border-radius: 50%;
            background: #ff445c;

            box-shadow:
                0 0 10px #ff445c;
        }

        .desc {

            margin-top: 25px;

            color: #89a2b4;

            line-height: 1.8;

            font-size: 18px;
        }

        .desc strong {
            color: white;
        }


        /* ======================================================
   RODAPÉ
====================================================== */

        .footer {

            position: absolute;

            bottom: 40px;
            left: 50%;

            transform: translateX(-50%);

            color: #4e6a7f;

            letter-spacing: 4px;
            font-size: 11px;
        }
    </style>
</head>

<body>

    <div class="grid"></div>

    <div class="particles" id="particles"></div>

    <header class="top">

        <div class="brand">

            <div class="logo">
                <img src="assets/tvmax-mark.svg">
            </div>

            <div class="brand-text">
                <h3>TVMAX</h3>
                <p>PLATAFORMA DE TRANSMISSÃO</p>
            </div>

        </div>

        <div class="live">
            <div class="live-dot"></div>
            AO VIVO
        </div>

    </header>

    <div class="center">

        <div class="hologram">

            <div class="orbit"></div>
            <div class="orbit orbit2"></div>
            <div class="orbit orbit3"></div>

            <div class="core-glow"></div>

            <div class="core">

                <div class="pause">
                    <span></span>
                    <span></span>
                </div>

            </div>

        </div>

        <div class="tag">
            TRANSMISSÃO
        </div>

        <h1>PAUSADA</h1>

        <div class="badge">
            <div class="badge-dot"></div>
            APRESENTAÇÃO AO VIVO
        </div>

        <div class="desc">
            Estamos <strong>ao vivo</strong>.<br>
            A apresentação foi pausada e será retomada automaticamente.
        </div>

    </div>

    <div class="footer">
        AGUARDANDO RETORNO DA TRANSMISSÃO
    </div>

    <script>

        /* PARTICULAS */

        const particles = document.getElementById('particles');

        for (let i = 0; i < 60; i++) {

            let p = document.createElement('span');

            p.style.left = Math.random() * 100 + '%';
            p.style.top = Math.random() * 100 + '%';

            p.style.animationDuration = (6 + Math.random() * 10) + 's';
            p.style.animationDelay = (Math.random() * 8) + 's';

            particles.appendChild(p);
        }

    </script>

</body>

</html>