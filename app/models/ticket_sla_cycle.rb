# frozen_string_literal: true

class TicketSlaCycle < ApplicationRecord
  belongs_to :ticket

  enum status: {
    running: "running",
    paused: "paused",
    completed: "completed",
    breached: "breached"
  }

  enum reason: {
    created: "created",
    customer_reply: "customer_reply",
    reopen: "reopen",
    closed: "closed"
  }

  scope :active, -> { where(status: %w[running paused]) }

  def pause!
    return if paused?

    update!(
      paused_at: Time.current,
      status: :paused,
      elapsed_seconds: compute_elapsed_seconds(Time.current)
    )
  end

  def end!(reason:)
    update!(
      status: :completed,
      reason: reason,
      elapsed_seconds: compute_elapsed_seconds(Time.current)
    )
  end

  private

  def compute_elapsed_seconds(reference_time)
    return elapsed_seconds if elapsed_seconds.present? && paused_at.present?
    return 0 unless started_at

    (reference_time - started_at).to_i
  end
end
