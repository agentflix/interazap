<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Avaliação de Atendimento</title>
    <style>
        body {
            margin: 0;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            background: #f7f7fb;
            color: #1f2937;
        }

        .container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .card {
            width: 100%;
            max-width: 520px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
        }

        h1 {
            margin: 0 0 8px;
            font-size: 24px;
            line-height: 32px;
        }

        p {
            margin: 0 0 16px;
            color: #4b5563;
        }

        .stars {
            display: flex;
            gap: 8px;
            margin: 12px 0 16px;
        }

        .star {
            border: none;
            background: transparent;
            font-size: 34px;
            line-height: 1;
            cursor: pointer;
            color: #d1d5db;
            transition: transform 0.12s ease;
        }

        .star.active {
            color: #f59e0b;
        }

        .star:hover {
            transform: scale(1.08);
        }

        textarea {
            width: 100%;
            min-height: 100px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            padding: 10px 12px;
            resize: vertical;
            font-size: 14px;
        }

        .submit {
            margin-top: 16px;
            width: 100%;
            border: none;
            border-radius: 10px;
            background: #2563eb;
            color: #ffffff;
            font-weight: 600;
            font-size: 15px;
            padding: 12px 16px;
            cursor: pointer;
        }

        .submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .error {
            color: #b91c1c;
            margin-top: 12px;
            font-size: 14px;
        }

        .success {
            display: none;
            text-align: center;
            padding: 8px 0;
        }

        .success h2 {
            margin: 0;
            font-size: 24px;
        }

        .success p {
            margin-top: 8px;
        }
    </style>
</head>
<body>
<main class="container">
    <section class="card">
        <div id="form-screen">
            <h1>Como foi seu atendimento?</h1>
            <p>Selecione uma nota de 1 a 5 estrelas e, se quiser, deixe um comentário.</p>

            <div class="stars" id="stars">
                <button type="button" class="star" data-rating="1" aria-label="1 estrela">★</button>
                <button type="button" class="star" data-rating="2" aria-label="2 estrelas">★</button>
                <button type="button" class="star" data-rating="3" aria-label="3 estrelas">★</button>
                <button type="button" class="star" data-rating="4" aria-label="4 estrelas">★</button>
                <button type="button" class="star" data-rating="5" aria-label="5 estrelas">★</button>
            </div>

            <label for="comment">Comentário (opcional)</label>
            <textarea id="comment" placeholder="Conte mais sobre sua experiência"></textarea>

            <button type="button" id="submit" class="submit">Enviar avaliação</button>
            <div id="error" class="error" role="alert"></div>
        </div>

        <div id="success-screen" class="success">
            <h2>Obrigado pelo seu feedback!</h2>
            <p>Sua avaliação foi registrada com sucesso.</p>
        </div>
    </section>
</main>

<script>
    (function () {
        const token = @json($token);
        const stars = Array.from(document.querySelectorAll('.star'));
        const comment = document.getElementById('comment');
        const submit = document.getElementById('submit');
        const error = document.getElementById('error');
        const formScreen = document.getElementById('form-screen');
        const successScreen = document.getElementById('success-screen');
        let selectedRating = 0;

        const paintStars = function (rating) {
            stars.forEach(function (star) {
                const starRating = Number(star.getAttribute('data-rating') || 0);
                star.classList.toggle('active', starRating <= rating);
            });
        };

        stars.forEach(function (star) {
            star.addEventListener('click', function () {
                selectedRating = Number(star.getAttribute('data-rating') || 0);
                paintStars(selectedRating);
                error.textContent = '';
            });
        });

        submit.addEventListener('click', async function () {
            if (selectedRating < 1 || selectedRating > 5) {
                error.textContent = 'Selecione uma nota de 1 a 5 estrelas.';
                return;
            }

            submit.disabled = true;
            error.textContent = '';

            try {
                const response = await fetch('/api/public/chat/evaluations/' + encodeURIComponent(token), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        rating: selectedRating,
                        comment: comment.value ? comment.value.trim() : null,
                    }),
                });

                if (!response.ok) {
                    throw new Error('Erro ao enviar avaliação.');
                }

                formScreen.style.display = 'none';
                successScreen.style.display = 'block';
            } catch (e) {
                error.textContent = 'Não foi possível enviar sua avaliação. Tente novamente.';
                submit.disabled = false;
            }
        });
    })();
</script>
</body>
</html>
