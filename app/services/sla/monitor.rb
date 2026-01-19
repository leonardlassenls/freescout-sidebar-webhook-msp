# frozen_string_literal: true

module Sla
  class Monitor
    def self.run
      Ticket.where.not(status: "closed").find_each do |ticket|
        Sla::Evaluator.call(ticket)
      end
    end
  end
end
