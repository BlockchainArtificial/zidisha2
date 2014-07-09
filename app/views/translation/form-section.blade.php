<h{{ $level }} id="{{ $group }}"> {{ ucfirst($title) }} </h{{ $level }}>

@foreach ($labels as $key => $value)
    @if (is_array($value))
        @include('translation.form-section', ['labels' => $value, 'level' => $level + 1, 'group' => $group . '_' . $key, 'title' => $key])
    @else
            {{ BootstrapForm::label($key, null, ['style' => 'display:none;']) }}
            <div class="row">
                <div class="col-md-6">
                    <p class="well">
                        {{ $value }}
                    </p>
                </div>

                <div class="col-md-6">
                    {{ BootstrapForm::textarea($group . '_' . str_replace('.', '_', $key), null, ['label' => false, 'rows' => 5]) }}
                </div>
            </div>
    @endif

@endforeach
