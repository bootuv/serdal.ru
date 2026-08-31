<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Квитанция № {{ $payment->id }} — Serdal</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, "Helvetica Neue", Helvetica, sans-serif;
            color: #202323;
            background: #f4f6f6;
            margin: 0;
            padding: 40px 16px;
        }

        .receipt {
            max-width: 640px;
            margin: 0 auto;
            background: #fff;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, .06);
        }

        .receipt__header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 28px;
        }

        .receipt__logo {
            height: 28px;
        }

        .receipt__title {
            margin: 24px 0 4px;
            font-size: 22px;
            font-weight: 700;
        }

        .receipt__subtitle {
            margin: 0 0 24px;
            color: #5f6262;
            font-size: 14px;
        }

        .receipt__status {
            display: inline-block;
            background: #e8f7ec;
            color: #1a7f37;
            font-size: 13px;
            font-weight: 600;
            border-radius: 999px;
            padding: 5px 14px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 15px;
        }

        td {
            padding: 10px 0;
            border-bottom: 1px solid #eef1f1;
            vertical-align: top;
        }

        td:first-child {
            color: #5f6262;
            width: 45%;
        }

        td:last-child {
            text-align: right;
            font-weight: 500;
        }

        .receipt__total td {
            border-bottom: none;
            padding-top: 16px;
            font-size: 18px;
            font-weight: 700;
        }

        .receipt__legal {
            margin-top: 28px;
            padding-top: 16px;
            border-top: 1px solid #eef1f1;
            color: #5f6262;
            font-size: 12px;
            line-height: 1.6;
        }

        .receipt__actions {
            max-width: 640px;
            margin: 20px auto 0;
            text-align: center;
        }

        .receipt__button {
            display: inline-block;
            background: #111;
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 12px 28px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
        }

        @media print {
            body {
                background: #fff;
                padding: 0;
            }

            .receipt {
                box-shadow: none;
                border-radius: 0;
                max-width: none;
            }

            .receipt__actions {
                display: none;
            }
        }
    </style>
</head>

<body>
    <div class="receipt">
        <div class="receipt__header">
            <img src="{{ asset('images/Logo.svg') }}" alt="Serdal" class="receipt__logo">
            <span class="receipt__status">Оплачено</span>
        </div>

        <h1 class="receipt__title">Квитанция об оплате № {{ $payment->id }}</h1>
        <p class="receipt__subtitle">от {{ ($payment->paid_at ?? $payment->created_at)->format('d.m.Y H:i') }}</p>

        <table>
            <tr>
                <td>Плательщик</td>
                <td>{{ $payment->user->name }}<br>{{ $payment->user->email }}</td>
            </tr>
            <tr>
                <td>Услуга</td>
                <td>Подписка на платформу Serdal, тариф «{{ $payment->tariff->name }}»
                    ({{ $payment->tariff->period_days }} дней)</td>
            </tr>
            <tr>
                <td>Способ оплаты</td>
                <td>Банковская карта{{ $payment->gateway === 'alfabank' ? ' (интернет-эквайринг Альфа-Банка)' : '' }}</td>
            </tr>
            @if($payment->gateway_order_id)
                <tr>
                    <td>Идентификатор операции</td>
                    <td>{{ $payment->gateway_order_id }}</td>
                </tr>
            @endif
            <tr class="receipt__total">
                <td>Итого</td>
                <td>{{ number_format($payment->amount, 0, ',', ' ') }} ₽</td>
            </tr>
        </table>

        <div class="receipt__legal">
            Получатель: {{ $legal['legal_name'] ?? 'Serdal' }}
            @if(!empty($legal['legal_inn']))
                · ИНН {{ $legal['legal_inn'] }}
            @endif
            @if(!empty($legal['legal_ogrn']))
                · ОГРН/ОГРНИП {{ $legal['legal_ogrn'] }}
            @endif
            <br>
            По вопросам оплаты: <a href="mailto:info@serdal.ru" style="color: #0066cc;">info@serdal.ru</a>.
            Документ сформирован автоматически на serdal.ru.
        </div>
    </div>

    <div class="receipt__actions">
        <button class="receipt__button" onclick="window.print()">Скачать PDF / Распечатать</button>
    </div>
</body>

</html>
