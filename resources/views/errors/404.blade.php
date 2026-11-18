@include('errors.layout', [
    'kode' => 404,
    'headline' => __('site.errors.404_title'),
    'pesan' => __('site.errors.404_body'),
])
