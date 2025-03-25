@extends('layouts.app')

@section('content')
@php use Illuminate\Support\Str; @endphp
<style>
    .form-group {
        margin-bottom: 1.5rem;
    }

    .dropzone-wrapper {
        border: 2px dashed #4e73df;
        border-radius: 10px;
        position: relative;
        cursor: pointer;
        padding: 20px;
        text-align: center;
        transition: background 0.3s ease;
        margin-top: 10px;
    }

    .dropzone-wrapper:hover {
        background: #f9f9f9;
    }

     
    .dropzone {
    opacity: 0;
    position: absolute;
    height: 100%;
    width: 100%;
    cursor: pointer;
    pointer-events: none; /* 🔥 This is the fix */
}


    .dropzone-desc {
        font-size: 14px;
        color: #888;
    }

    .dropzone-wrapper.dragover {
        background: #e2eefd;
        border-color: #2653d4;
    }

    .img-thumbnail {
        margin-top: 10px;
        max-width: 120px;
    }

    .spinner-border {
        display: inline-block;
        width: 1rem;
        height: 1rem;
        vertical-align: text-bottom;
        border: 0.15em solid currentColor;
        border-right-color: transparent;
        border-radius: 50%;
        animation: spinner-border .75s linear infinite;
    }

    .file-preview {
        margin-top: 10px;
        font-size: 0.875rem;
        color: #333;
    }

    .file-preview img {
        max-width: 100px;
        margin-top: 5px;
        border-radius: 6px;
    }

    @keyframes spinner-border {
        100% {
            transform: rotate(360deg);
        }
    }
</style>

<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="row">
    <div class="col-md-12">
        <div class="card mb-4">
            <div class="card-body">
                <div class="card-title mb-3">Upload Member Documents</div>

                <div id="uploadStatus"></div>

                <form method="POST" action="{{ route('members.edit.update.image', $member->member_id) }}" enctype="multipart/form-data" id="uploadForm">
                    @csrf
                    <div class="row">

                        @php
                        $fields = [
                        'member_image' => 'Passport Photo',
                        'member_signature' => 'Signature',
                        'member_id_copy_front' => 'ID Copy Front',
                        'member_id_copy_back' => 'ID Copy Back',
                        'member_payslips_bank_statements' => 'Payslips or Bank Statement'
                        ];
                        @endphp

                        @foreach ($fields as $name => $label)
                        <div class="col-md-6 form-group">
                            <label for="{{ $name }}">{{ $label }} (Max: 280KB)</label>

                            <div class="dropzone-wrapper" data-target="{{ $name }}">
                                <div class="dropzone-desc">
                                    <p>Drag & drop or click to upload</p>
                                </div>
                                <input type="file" name="{{ $name }}" class="dropzone form-control"
                                    accept="{{ $name === 'member_payslips_bank_statements' ? '.pdf,image/*' : 'image/*' }}"
                                    capture="environment">
                            </div>

                            <div class="file-preview"></div>

                            @if(auth()->user()->member_position == 2 && $member->$name)
                            @php
                            $filePath = $member->$name;
                            $fileUrl = route('members.protectedfile', ['path' => urlencode($filePath)]);
                            $isImage = Str::endsWith(strtolower($filePath), ['.jpg', '.jpeg', '.png', '.webp', '.gif']);
                            @endphp

                            <div class="mt-2">
                                @if($isImage)
                                <img src="{{ $fileUrl }}" alt="{{ $label }}" class="img-thumbnail">
                                @else
                                <a href="{{ $fileUrl }}" target="_blank" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-file-earmark-pdf"></i> View PDF Document
                                </a>
                                @endif
                            </div>
                            @endif
                        </div>
                        @endforeach

                        <div class="col-md-12 mt-3">
                            <progress id="uploadProgress" value="0" max="100" style="width: 100%; display: none;"></progress>
                        </div>

                        <div class="col-md-12 mt-3">
                            <button type="submit" class="btn btn-primary px-4 py-2">
                                Upload Documents
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
 
<script>
    
    document.querySelectorAll('.dropzone-wrapper').forEach(wrapper => {
    const input = wrapper.querySelector('input[type="file"]');
    const preview = wrapper.querySelector('.file-preview');
    const clickableArea = wrapper.querySelector('.dropzone-desc'); // Only this triggers upload

    const showPreview = (file) => {
        preview.innerHTML = '';
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = e => {
                const img = document.createElement('img');
                img.src = e.target.result;
                preview.appendChild(img);
            };
            reader.readAsDataURL(file);
        } else {
            const name = document.createElement('div');
            name.textContent = file.name;
            preview.appendChild(name);
        }
    };

    input.addEventListener('change', () => {
        if (input.files.length > 0) showPreview(input.files[0]);
    });

    // ✅ Only click on dropzone-desc opens file input
    clickableArea.addEventListener('click', () => input.click());

    wrapper.addEventListener('dragover', e => {
        e.preventDefault();
        wrapper.classList.add('dragover');
    });

    wrapper.addEventListener('dragleave', () => {
        wrapper.classList.remove('dragover');
    });

    wrapper.addEventListener('drop', e => {
        e.preventDefault();
        wrapper.classList.remove('dragover');
        if (e.dataTransfer.files.length > 0) {
            input.files = e.dataTransfer.files;
            showPreview(e.dataTransfer.files[0]);
        }
    });
});


    // ✅ This must be outside the dropzone-wrapper loop
    document.querySelectorAll('.file-preview, .file-preview img, .file-preview a').forEach(el => {
        el.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    });

    const form = document.getElementById('uploadForm');
    const progress = document.getElementById('uploadProgress');
    const submitBtn = form.querySelector('button[type="submit"]');
    const uploadStatus = document.getElementById('uploadStatus');

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(form);
        const hasFile = [...formData.values()].some(val => val instanceof File && val.name !== "");

        if (!hasFile) {
            uploadStatus.innerHTML = `<div class="alert alert-warning">Please select a file to upload.</div>`;
            return;
        }

        submitBtn.disabled = true;
        const originalBtnText = submitBtn.innerHTML;
        submitBtn.innerHTML = `<span class="spinner-border" role="status" aria-hidden="true"></span> Uploading...`;

        const xhr = new XMLHttpRequest();
        xhr.open('POST', form.action, true);
        xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));

        xhr.upload.onprogress = function(e) {
            if (e.lengthComputable) {
                progress.style.display = 'block';
                progress.value = (e.loaded / e.total) * 100;
            }
        };

        xhr.onload = function() {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;

            try {
                const response = JSON.parse(xhr.responseText);
                if (response.success) {
                    uploadStatus.innerHTML = `<div class="alert alert-success">Upload successful.</div>`;
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    if (response.errors) {
                        Object.keys(response.errors).forEach(field => {
                            const input = document.querySelector(`[name="${field}"]`);
                            if (input) {
                                const errorDiv = document.createElement('div');
                                errorDiv.className = 'text-danger mt-1';
                                errorDiv.textContent = response.errors[field][0];
                                input.closest('.form-group').appendChild(errorDiv);
                            }
                        });
                        uploadStatus.innerHTML = `<div class="alert alert-danger">Fix the highlighted issues below.</div>`;
                    } else {
                        uploadStatus.innerHTML = `<div class="alert alert-danger">${response.message || 'Upload failed.'}</div>`;
                    }
                }
            } catch {
                window.location.reload(); // fallback
            }
        };

        xhr.onerror = function() {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
            uploadStatus.innerHTML = `<div class="alert alert-danger">Network error. Please try again.</div>`;
        };

        document.querySelectorAll('.text-danger').forEach(el => el.remove());

        xhr.send(formData);
    });
</script>
@endsection