@include('errors.layout', [
    'kode' => 500,
    'headline' => __('site.errors.500_title'),
    'pesan' => __('site.errors.500_body'),
])
