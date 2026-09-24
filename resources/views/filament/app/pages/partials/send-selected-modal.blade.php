<div class="space-y-3 text-sm">
    <div>
        <span class="font-medium">Selected:</span>
        {{ $jobs }} {{ $jobs === 1 ? 'job' : 'jobs' }} at {{ $companies }} {{ $companies === 1 ? 'company' : 'companies' }}
        <span class="text-gray-500">(one application per company)</span>
    </div>
    <div>
        <span class="font-medium">Companies:</span>
        {{ implode(', ', $names) }}@if ($more > 0) and {{ $more }} more @endif
    </div>
    <div><span class="font-medium">Attachment:</span> {{ $attachment }}</div>
    @if ($subject !== null && $subject !== '')
        <div><span class="font-medium">Subject:</span> {{ $subject }}</div>
    @endif
    <div>{{ $remaining }} of {{ $limit }} applications left today</div>
    @if ($overLimit !== null)
        <p class="font-medium text-danger-600 dark:text-danger-400">{{ $overLimit }}</p>
    @endif
</div>
