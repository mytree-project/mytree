<?php

declare(strict_types=1);

return [
    'technical_details' => 'Technical details',
    'mention_json_syntax' => 'JSON syntax error in Mention no. :number:key_suffix.',
    'mention_json_object' => 'JSON data in Mention no. :number:key_suffix must be a JSON object.',
    'mention_required' => 'Mention no. :number:key_suffix requires a kind and local key.',
    'mention_invalid' => 'Mention no. :number:key_suffix contains invalid data.',
    'mention_duplicate_local_key' => 'The local key in Mention no. :number:key_suffix must be unique within the Source.',
    'claim_subject_required' => 'Claim no. :number requires a subject Mention.',
    'claim_subject_missing' => 'Claim no. :number refers to a subject Mention that does not exist.',
    'claim_object_required' => 'Claim no. :number requires an object Mention.',
    'claim_object_missing' => 'Claim no. :number refers to an object Mention that does not exist.',
    'claim_value_required' => 'Claim no. :number requires a source value.',
    'claim_effective_time_incomplete' => 'Claim no. :number has an incomplete effective-time expression.',
    'claim_invalid' => 'Claim no. :number contains invalid data.',
    'event_invalid' => 'Event no. :number:key_suffix contains invalid data.',
    'event_claim_invalid' => 'Claim no. :claim_number in Event no. :event_number contains invalid data.',
    'metadata_invalid' => 'Metadata field no. :number contains invalid data.',
    'metadata_integer_invalid' => 'Metadata field no. :number requires a valid integer.',
    'metadata_number_invalid' => 'Metadata field no. :number requires a valid number.',
    'metadata_boolean_invalid' => 'Metadata field no. :number requires true or false.',
    'metadata_duplicate' => 'The key of metadata field no. :number must be unique.',
    'source_text_invalid' => 'Source text representation no. :number contains invalid data.',
    'upload_invalid' => 'The attached file could not be read or validated correctly.',
    'source_details_invalid' => 'Source details contain invalid or incomplete data.',
    'workspace_data_invalid' => 'Workspace data contains an error and cannot be saved.',
];
