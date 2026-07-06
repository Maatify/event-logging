CREATE TABLE maa_event_logging_behavior_trace (
                                      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                                      event_id CHAR(36) NOT NULL,

                                      actor_type VARCHAR(32) NOT NULL,
                                      actor_id BIGINT NULL,

    -- Examples: create/update/delete/bulk_action/report_run...
                                      action VARCHAR(128) NOT NULL,

    -- Affected entity (optional)
                                      entity_type VARCHAR(64) NULL,
                                      entity_id BIGINT NULL,

    -- Metadata about the operation (MUST NOT contain secrets)
                                      metadata JSON NOT NULL,

                                      correlation_id CHAR(36) NULL,
                                      request_id VARCHAR(64) NULL,
                                      route_name VARCHAR(255) NULL,

                                      ip_address VARCHAR(45) NULL,
                                      user_agent VARCHAR(512) NULL,

                                      occurred_at DATETIME(6) NOT NULL,

                                      UNIQUE KEY uq_el_behavior_trace_event_id (event_id),

    -- Cursor index for stable paging / future batch processing
                                      INDEX idx_el_behavior_trace_time (occurred_at, id),

    -- Required search dimensions
                                      INDEX idx_el_behavior_trace_actor_time (actor_type, actor_id, occurred_at),
                                      INDEX idx_el_behavior_trace_action_time (action, occurred_at),

    -- Deep investigations
                                      INDEX idx_el_behavior_trace_entity_time (entity_type, entity_id, occurred_at),

    -- Correlation helpers
                                      INDEX idx_el_behavior_trace_corr_time (correlation_id, occurred_at),
                                      INDEX idx_el_behavior_trace_request_time (request_id, occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Operational activity for mutations only. Searchable by actor+time and action+time. Views/reads are NOT allowed (use maa_event_logging_audit_trail).';
