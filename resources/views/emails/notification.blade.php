@extends('emails.layout')

@section('content')
    <h2>{{ $title }}</h2>
    
    <p>{{ $greeting }},</p>

    @if(!empty($intro))
        <p>{{ $intro }}</p>
    @endif

    @if(!empty($fields))
        <div class="info-box">
            <table class="info-table">
                @foreach($fields as $label => $value)
                    <tr>
                        <th>{{ $label }}</th>
                        <td>{{ $value }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

    @if(!empty($message_body))
        <p style="white-space: pre-line;">{{ $message_body }}</p>
    @endif

    @if(!empty($action_url))
        <div style="text-align: center; margin-top: 10px;">
            <a href="{{ $action_url }}" class="button">{{ $action_text ?? 'Xem chi tiết' }}</a>
        </div>
    @endif

    @if(!empty($policies))
        <div class="policy-box">
            <strong style="color: #111111; display: block; margin-bottom: 10px;">Chính sách nhà hàng:</strong>
            <ul style="margin: 0; padding-left: 20px; line-height: 1.5;">
                @foreach($policies as $policy)
                    <li style="margin-bottom: 5px;">{{ $policy }}</li>
                @endforeach
            </ul>
        </div>
    @endif
@endsection
