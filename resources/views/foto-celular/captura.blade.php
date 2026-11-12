<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Foto do aluno</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "Open Sans", sans-serif; background: #10181d; color: #fff; }
        main { max-width: 36rem; margin: 0 auto; padding: 1rem 1rem 2rem; }
        h1 { font-size: 1.25rem; margin: 0 0 0.35rem; }
        p { margin: 0 0 0.85rem; line-height: 1.4; color: #d5dee4; }
        .stage { position: relative; background: #000; border-radius: 8px; overflow: hidden; min-height: 16rem; }
        video, canvas { width: 100%; height: auto; display: block; }
        .recorte canvas { width: auto; max-width: 100%; }
        video[hidden], canvas[hidden], .recorte[hidden], [hidden] { display: none !important; }
        .recorte { position: relative; width: fit-content; max-width: 100%; margin: 0 auto; }
        .moldura { position: absolute; border: 2px solid #fff; box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.55); touch-action: none; cursor: grab; }
        .moldura span { position: absolute; top: 6px; left: 6px; font-size: 12px; text-shadow: 0 1px 2px #000; }
        .acoes { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.85rem; }
        button { border: 0; border-radius: 6px; padding: 0.8rem 1rem; font-size: 1rem; cursor: pointer; }
        .primario { background: #0ac336; color: #fff; }
        .secundario { background: #e9f0f8; color: #24343c; }
        .erro { color: #ffb4b4; }
        .sucesso { background: #fff; color: #24343c; border-radius: 8px; padding: 1.25rem; }
        button:disabled { opacity: 0.6; }
    </style>
</head>
<body>
<main>
    <section id="captura">
        <h1>Foto do aluno</h1>
        <p id="instrucao">Posicione o aluno, capture a foto e ajuste o retângulo 3x4. O código vale {{ $expiraEmMinutos }} minutos.</p>
        <p id="erro" class="erro" hidden></p>
        <div class="stage">
            <video id="video" autoplay playsinline muted hidden></video>
            <div id="recorte" class="recorte" hidden>
                <canvas id="foto"></canvas>
                <div id="moldura" class="moldura" tabindex="0" aria-label="Área de corte 3 por 4. Arraste para posicionar."><span>3×4</span></div>
            </div>
        </div>
        <div class="acoes">
            <button type="button" id="ao-vivo" class="primario" hidden>Usar câmera agora</button>
            <button type="button" id="capturar" class="primario" hidden>Capturar</button>
            <button type="button" id="outra" class="secundario" hidden>Trocar foto</button>
            <button type="button" id="remover-fundo" class="secundario" hidden>Remover fundo</button>
            <button type="button" id="enviar" class="primario" hidden>Enviar foto</button>
        </div>
    </section>
    <section id="enviada" class="sucesso" hidden>
        <h1>Foto enviada</h1>
        <p>Volte ao computador. A foto já está no cadastro e será salva quando o aluno for gravado.</p>
    </section>
</main>
<script>
(function () {
    var PROPORCAO_LARGURA = 3;
    var PROPORCAO_ALTURA = 4;
    var LADO_MAXIMO = 1600;
    var video = document.getElementById("video");
    var recorte = document.getElementById("recorte");
    var canvas = document.getElementById("foto");
    var moldura = document.getElementById("moldura");
    var erro = document.getElementById("erro");
    var aoVivo = document.getElementById("ao-vivo");
    var capturar = document.getElementById("capturar");
    var outra = document.getElementById("outra");
    var removerFundo = document.getElementById("remover-fundo");
    var enviar = document.getElementById("enviar");
    var stream = null;
    var segmentador = null;
    var carregandoSegmentador = null;
    var fotoOriginal = null;
    var fundoRemovido = false;
    var arrastando = false;
    var origemX = 0;
    var origemY = 0;
    var inicioLeft = 0;
    var inicioTop = 0;

    if (!window.isSecureContext || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        aoVivo.hidden = true;
        mostrarErro("A câmera só abre em uma conexão segura (HTTPS).");
    }

    function mostrarErro(mensagem) {
        erro.textContent = mensagem || "";
        erro.hidden = !mensagem;
    }

    function pararCamera() {
        if (stream) {
            stream.getTracks().forEach(function (track) { track.stop(); });
            stream = null;
        }
        video.srcObject = null;
    }

    function limitarMoldura(left, top) {
        var maxLeft = Math.max(0, recorte.clientWidth - moldura.offsetWidth);
        var maxTop = Math.max(0, recorte.clientHeight - moldura.offsetHeight);
        moldura.style.left = Math.max(0, Math.min(maxLeft, left)) + "px";
        moldura.style.top = Math.max(0, Math.min(maxTop, top)) + "px";
    }

    function posicionarMoldura() {
        var proporcao = canvas.width / canvas.height;
        if (!proporcao) return;
        var largura = Math.min(window.innerWidth - 32, 560);
        var altura = largura / proporcao;
        var alturaMaxima = Math.max(220, window.innerHeight * 0.55);
        if (altura > alturaMaxima) {
            altura = alturaMaxima;
            largura = altura * proporcao;
        }
        canvas.style.width = Math.round(largura) + "px";
        canvas.style.height = Math.round(altura) + "px";
        var larguraImagem = canvas.clientWidth;
        var alturaImagem = canvas.clientHeight;
        if (!larguraImagem || !alturaImagem) return;
        var largura = larguraImagem;
        var altura = largura * PROPORCAO_ALTURA / PROPORCAO_LARGURA;
        if (altura > alturaImagem) {
            altura = alturaImagem;
            largura = altura * PROPORCAO_LARGURA / PROPORCAO_ALTURA;
        }
        moldura.style.width = largura + "px";
        moldura.style.height = altura + "px";
        limitarMoldura((larguraImagem - largura) / 2, (alturaImagem - altura) / 2);
    }

    function mostrarRecorte() {
        pararCamera();
        video.hidden = true;
        recorte.hidden = false;
        aoVivo.hidden = true;
        capturar.hidden = true;
        outra.hidden = false;
        removerFundo.hidden = false;
        removerFundo.disabled = false;
        removerFundo.textContent = fundoRemovido ? "Restaurar fundo" : "Remover fundo";
        enviar.hidden = false;
        posicionarMoldura();
        requestAnimationFrame(posicionarMoldura);
    }

    function desenhar(imagem) {
        var largura = imagem.videoWidth || imagem.naturalWidth || imagem.width;
        var altura = imagem.videoHeight || imagem.naturalHeight || imagem.height;
        var escala = Math.min(1, LADO_MAXIMO / Math.max(largura, altura));
        canvas.width = Math.max(1, Math.round(largura * escala));
        canvas.height = Math.max(1, Math.round(altura * escala));
        canvas.getContext("2d").drawImage(imagem, 0, 0, canvas.width, canvas.height);
        if (imagem.close) imagem.close();
        fotoOriginal = document.createElement("canvas");
        fotoOriginal.width = canvas.width;
        fotoOriginal.height = canvas.height;
        fotoOriginal.getContext("2d").drawImage(canvas, 0, 0);
        fundoRemovido = false;
        mostrarErro("");
        mostrarRecorte();
    }

    function restaurarFundo() {
        if (!fotoOriginal) return;
        canvas.width = fotoOriginal.width;
        canvas.height = fotoOriginal.height;
        canvas.getContext("2d").drawImage(fotoOriginal, 0, 0);
        fundoRemovido = false;
        removerFundo.textContent = "Remover fundo";
        posicionarMoldura();
    }

    function carregarSegmentador() {
        if (segmentador) return Promise.resolve(segmentador);
        if (carregandoSegmentador) return carregandoSegmentador;
        carregandoSegmentador = new Promise(function (resolve, reject) {
            var script = document.createElement("script");
            script.src = "/vendor/selfie-segmentation/selfie_segmentation.js";
            script.onload = function () {
                try {
                    var instancia = new SelfieSegmentation({
                        locateFile: function (arquivo) {
                            return "/vendor/selfie-segmentation/" + arquivo;
                        }
                    });
                    instancia.setOptions({ modelSelection: 0, selfieMode: false });
                    segmentador = instancia;
                    resolve(instancia);
                } catch (erro) {
                    carregandoSegmentador = null;
                    reject(erro);
                }
            };
            script.onerror = function () {
                carregandoSegmentador = null;
                reject(new Error("script"));
            };
            document.head.appendChild(script);
        });
        return carregandoSegmentador;
    }

    function aplicarMascara(resultados) {
        var largura = canvas.width;
        var altura = canvas.height;
        var pessoa = document.createElement("canvas");
        pessoa.width = largura;
        pessoa.height = altura;
        var recortePessoa = pessoa.getContext("2d");
        recortePessoa.drawImage(resultados.segmentationMask, 0, 0, largura, altura);
        recortePessoa.globalCompositeOperation = "source-in";
        recortePessoa.drawImage(resultados.image, 0, 0, largura, altura);

        var saida = canvas.getContext("2d");
        saida.globalCompositeOperation = "source-over";
        saida.fillStyle = "#ffffff";
        saida.fillRect(0, 0, largura, altura);
        saida.drawImage(pessoa, 0, 0);
    }

    function removerFundoDaFoto() {
        if (fundoRemovido) {
            restaurarFundo();
            return;
        }
        removerFundo.disabled = true;
        removerFundo.textContent = "Removendo fundo...";
        mostrarErro("");
        carregarSegmentador().then(function (instancia) {
            return new Promise(function (resolve, reject) {
                instancia.onResults(resolve);
                instancia.send({ image: canvas }).catch(reject);
            });
        }).then(function (resultados) {
            aplicarMascara(resultados);
            fundoRemovido = true;
            removerFundo.textContent = "Restaurar fundo";
            posicionarMoldura();
        }).catch(function () {
            removerFundo.textContent = "Remover fundo";
            mostrarErro("Não foi possível remover o fundo. Tente de novo.");
        }).then(function () {
            removerFundo.disabled = false;
        });
    }

    function abrirCamera() {
        mostrarErro("");
        navigator.mediaDevices.getUserMedia({
            audio: false,
            video: { facingMode: "environment" }
        }).then(function (midia) {
            stream = midia;
            video.srcObject = midia;
            video.hidden = false;
            recorte.hidden = true;
            aoVivo.hidden = true;
            capturar.hidden = false;
            return video.play();
        }).catch(function () {
            pararCamera();
            aoVivo.hidden = false;
            mostrarErro("Toque em Usar câmera agora e permita o acesso à câmera traseira.");
        });
    }

    aoVivo.addEventListener("click", abrirCamera);
    capturar.addEventListener("click", function () {
        if (!video.videoWidth) {
            mostrarErro("Aguarde a câmera iniciar.");
            return;
        }
        desenhar(video);
    });
    outra.addEventListener("click", function () {
        recorte.hidden = true;
        outra.hidden = true;
        enviar.hidden = true;
        removerFundo.hidden = true;
        capturar.hidden = true;
        abrirCamera();
    });
    removerFundo.addEventListener("click", removerFundoDaFoto);

    if (window.isSecureContext && navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
        abrirCamera();
    }

    moldura.addEventListener("pointerdown", function (event) {
        arrastando = true;
        origemX = event.clientX;
        origemY = event.clientY;
        inicioLeft = moldura.offsetLeft;
        inicioTop = moldura.offsetTop;
        moldura.setPointerCapture(event.pointerId);
        event.preventDefault();
    });
    moldura.addEventListener("pointermove", function (event) {
        if (!arrastando) return;
        limitarMoldura(inicioLeft + (event.clientX - origemX), inicioTop + (event.clientY - origemY));
    });
    moldura.addEventListener("pointerup", function () { arrastando = false; });
    moldura.addEventListener("pointercancel", function () { arrastando = false; });

    enviar.addEventListener("click", function () {
        var foto = canvas.getBoundingClientRect();
        var corte = moldura.getBoundingClientRect();
        if (!foto.width || !corte.width) return;
        var escalaX = canvas.width / foto.width;
        var escalaY = canvas.height / foto.height;
        var sx = Math.max(0, (corte.left - foto.left) * escalaX);
        var sy = Math.max(0, (corte.top - foto.top) * escalaY);
        var sw = Math.max(1, Math.min(corte.width * escalaX, canvas.width - sx));
        var sh = Math.max(1, Math.min(corte.height * escalaY, canvas.height - sy));
        var destino = document.createElement("canvas");
        destino.height = Math.min(800, Math.round(sh));
        destino.width = Math.max(1, Math.round(destino.height * PROPORCAO_LARGURA / PROPORCAO_ALTURA));
        destino.getContext("2d").drawImage(canvas, sx, sy, sw, sh, 0, 0, destino.width, destino.height);
        enviar.disabled = true;
        destino.toBlob(function (blob) {
            if (!blob) {
                enviar.disabled = false;
                mostrarErro("Não foi possível preparar a foto.");
                return;
            }
            var dados = new FormData();
            dados.append("foto", blob, "foto-aluno.jpg");
            var token = document.querySelector('meta[name="csrf-token"]');
            fetch(window.location.pathname, {
                method: "POST",
                body: dados,
                headers: {
                    "Accept": "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                    "X-CSRF-TOKEN": token ? token.content : ""
                }
            }).then(function (resposta) {
                return resposta.json().then(function (corpo) {
                    return { ok: resposta.ok, corpo: corpo };
                });
            }).then(function (resultado) {
                if (!resultado.ok) {
                    enviar.disabled = false;
                    mostrarErro(resultado.corpo.mensagem || "Não foi possível enviar a foto.");
                    return;
                }
                document.getElementById("captura").hidden = true;
                document.getElementById("enviada").hidden = false;
                pararCamera();
            }).catch(function () {
                enviar.disabled = false;
                mostrarErro("Sem conexão com o computador. Tente enviar de novo.");
            });
        }, "image/jpeg", 0.92);
    });

    window.addEventListener("pagehide", pararCamera);
})();
</script>
</body>
</html>
